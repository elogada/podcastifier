<?php

declare(strict_types=1);

function archive_support_available(): bool
{
    return class_exists('ZipArchive') || function_exists('gzinflate');
}

function zip_get_entry_contents(string $zipPath, string $entryName): ?string
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $contents = $zip->getFromName($entryName);
        $zip->close();
        return is_string($contents) ? $contents : null;
    }

    $entries = pure_php_zip_entries($zipPath);
    foreach ($entries as $entry) {
        if ($entry['name'] === $entryName) {
            if ($entry['is_dir']) {
                return null;
            }

            return pure_php_zip_read_entry($zipPath, $entry);
        }
    }

    return null;
}

function zip_extract_archive(string $zipPath, string $destination): void
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open ZIP archive.');
        }

        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new RuntimeException('Could not extract ZIP archive.');
        }

        $zip->close();
        return;
    }

    foreach (pure_php_zip_entries($zipPath) as $entry) {
        $entryPath = pure_php_zip_safe_path($destination, $entry['name']);
        if ($entryPath === null) {
            continue;
        }

        if ($entry['is_dir']) {
            if (!is_dir($entryPath) && !@mkdir($entryPath, 0777, true) && !is_dir($entryPath)) {
                throw new RuntimeException('Could not create directory: ' . $entryPath);
            }
            continue;
        }

        $directory = dirname($entryPath);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create directory: ' . $directory);
        }

        $contents = pure_php_zip_read_entry($zipPath, $entry);
        if (file_put_contents($entryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write extracted file: ' . $entry['name']);
        }
    }
}

function pure_php_zip_entries(string $zipPath): array
{
    $handle = @fopen($zipPath, 'rb');
    if (!is_resource($handle)) {
        throw new RuntimeException('Could not open ZIP archive: ' . $zipPath);
    }

    $stat = fstat($handle);
    $size = (int) ($stat['size'] ?? 0);
    $searchSize = min($size, 66000);

    fseek($handle, $size - $searchSize);
    $tail = (string) fread($handle, $searchSize);
    fclose($handle);

    $eocdPos = strrpos($tail, "PK\x05\x06");
    if ($eocdPos === false) {
        throw new RuntimeException('ZIP end-of-central-directory record was not found.');
    }

    $eocd = substr($tail, $eocdPos, 22);
    if (strlen($eocd) < 22) {
        throw new RuntimeException('ZIP end-of-central-directory record is incomplete.');
    }

    $parts = unpack('Vsignature/vdisk/vdisk_start/ventries_disk/ventries_total/Vdirectory_size/Vdirectory_offset/vcomment_length', $eocd);
    if (!is_array($parts) || ($parts['signature'] ?? 0) !== 0x06054b50) {
        throw new RuntimeException('ZIP end-of-central-directory record is invalid.');
    }

    $directoryOffset = (int) $parts['directory_offset'];
    $entriesTotal = (int) $parts['entries_total'];

    $handle = @fopen($zipPath, 'rb');
    if (!is_resource($handle)) {
        throw new RuntimeException('Could not reopen ZIP archive: ' . $zipPath);
    }

    fseek($handle, $directoryOffset);
    $entries = [];

    for ($i = 0; $i < $entriesTotal; $i++) {
        $header = fread($handle, 46);
        if ($header === false || strlen($header) < 46) {
            fclose($handle);
            throw new RuntimeException('ZIP central directory is incomplete.');
        }

        $info = unpack(
            'Vsignature/vversion_made/vversion_needed/vflags/vcompression/vmod_time/vmod_date/Vcrc/Vcompressed_size/Vuncompressed_size/vfilename_length/vextra_length/vcomment_length/vdisk_number/vinternal_attributes/Vexternal_attributes/Vlocal_header_offset',
            $header
        );

        if (!is_array($info) || ($info['signature'] ?? 0) !== 0x02014b50) {
            fclose($handle);
            throw new RuntimeException('ZIP central directory entry is invalid.');
        }

        $filename = (string) fread($handle, (int) $info['filename_length']);
        $extraLength = (int) $info['extra_length'];
        $commentLength = (int) $info['comment_length'];
        if ($extraLength > 0) {
            fread($handle, $extraLength);
        }
        if ($commentLength > 0) {
            fread($handle, $commentLength);
        }

        $entries[] = [
            'name' => str_replace('\\', '/', $filename),
            'compression' => (int) $info['compression'],
            'flags' => (int) $info['flags'],
            'compressed_size' => (int) $info['compressed_size'],
            'uncompressed_size' => (int) $info['uncompressed_size'],
            'local_header_offset' => (int) $info['local_header_offset'],
            'is_dir' => str_ends_with($filename, '/'),
        ];
    }

    fclose($handle);
    return $entries;
}

function pure_php_zip_read_entry(string $zipPath, array $entry): string
{
    if (($entry['flags'] & 0x0001) !== 0) {
        throw new RuntimeException('Encrypted ZIP entries are not supported in pure PHP mode.');
    }

    $handle = @fopen($zipPath, 'rb');
    if (!is_resource($handle)) {
        throw new RuntimeException('Could not open ZIP archive for entry extraction.');
    }

    fseek($handle, (int) $entry['local_header_offset']);
    $localHeader = fread($handle, 30);
    if ($localHeader === false || strlen($localHeader) < 30) {
        fclose($handle);
        throw new RuntimeException('ZIP local file header is incomplete.');
    }

    $info = unpack('Vsignature/vversion_needed/vflags/vcompression/vmod_time/vmod_date/Vcrc/Vcompressed_size/Vuncompressed_size/vfilename_length/vextra_length', $localHeader);
    if (!is_array($info) || ($info['signature'] ?? 0) !== 0x04034b50) {
        fclose($handle);
        throw new RuntimeException('ZIP local file header is invalid.');
    }

    $skip = (int) $info['filename_length'] + (int) $info['extra_length'];
    if ($skip > 0) {
        fread($handle, $skip);
    }

    $compressed = (string) fread($handle, (int) $entry['compressed_size']);
    fclose($handle);

    return pure_php_zip_inflate($compressed, (int) $entry['compression'], (int) $entry['uncompressed_size']);
}

function pure_php_zip_inflate(string $contents, int $compression, int $expectedSize): string
{
    if ($compression === 0) {
        return $contents;
    }

    if ($compression !== 8) {
        throw new RuntimeException('Only stored and deflated ZIP entries are supported in pure PHP mode.');
    }

    if (!function_exists('gzinflate')) {
        throw new RuntimeException('Deflated ZIP entries require zlib support.');
    }

    $data = @gzinflate($contents);
    if ($data === false) {
        $data = @gzinflate(substr($contents, 2));
    }

    if ($data === false) {
        throw new RuntimeException('Could not inflate ZIP entry contents.');
    }

    if ($expectedSize > 0 && strlen($data) !== $expectedSize) {
        return $data;
    }

    return $data;
}

function pure_php_zip_safe_path(string $destination, string $entryName): ?string
{
    $entryName = str_replace('\\', '/', $entryName);
    $entryName = ltrim($entryName, '/');
    if ($entryName === '') {
        return null;
    }

    $segments = array_values(array_filter(explode('/', $entryName), static fn (string $segment): bool => $segment !== ''));
    foreach ($segments as $segment) {
        if ($segment === '.' || $segment === '..' || str_contains($segment, ':')) {
            return null;
        }
    }

    return $destination . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
}
