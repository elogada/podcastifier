param(
    [Parameter(Position = 0)]
    [string]$Action = 'synth',
    [Parameter(Position = 1)]
    [string]$PiperExePath,
    [Parameter(Position = 2)]
    [string]$ModelPath,
    [Parameter(Position = 3)]
    [string]$ConfigPath,
    [Parameter(Position = 4)]
    [string]$EspeakDataPath,
    [Parameter(Position = 5)]
    [string]$InputPath,
    [Parameter(Position = 6)]
    [string]$PendingPath,
    [Parameter(Position = 7)]
    [string]$OutputPath,
    [Parameter(Position = 8)]
    [string]$StatusFilePath,
    [Parameter(Position = 9)]
    [string]$VoiceLabel
)

function Write-Status {
    param(
        [string]$State,
        [string]$Message,
        [string]$ErrorMessage = ''
    )

    if ([string]::IsNullOrWhiteSpace($StatusFilePath)) {
        return
    }

    $payload = [ordered]@{
        state      = $State
        message    = $Message
        voice      = $VoiceLabel
        updated_at = (Get-Date).ToString('o')
        error      = $(if ([string]::IsNullOrWhiteSpace($ErrorMessage)) { $null } else { $ErrorMessage })
    }

    $json = $payload | ConvertTo-Json -Depth 4
    Set-Content -LiteralPath $StatusFilePath -Value $json -Encoding UTF8
}

if ($Action -ne 'synth') {
    Write-Error 'Unknown action. Use synth.'
    exit 1
}

try {
    if (-not (Test-Path -LiteralPath $PiperExePath)) {
        throw 'Piper runtime was not found.'
    }

    if (-not (Test-Path -LiteralPath $ModelPath)) {
        throw 'Piper voice model was not found.'
    }

    if (-not (Test-Path -LiteralPath $ConfigPath)) {
        throw 'Piper voice config was not found.'
    }

    if (-not (Test-Path -LiteralPath $EspeakDataPath)) {
        throw 'Piper espeak-ng-data folder was not found.'
    }

    if (-not (Test-Path -LiteralPath $InputPath)) {
        throw 'Input text file was not found.'
    }

    $text = Get-Content -LiteralPath $InputPath -Raw -Encoding UTF8
    if ([string]::IsNullOrWhiteSpace($text)) {
        throw 'Input text is empty.'
    }

    $directory = Split-Path -Parent $OutputPath
    if (-not (Test-Path -LiteralPath $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    Write-Status -State 'processing' -Message 'Generating audio...'

    if (Test-Path -LiteralPath $PendingPath) {
        Remove-Item -LiteralPath $PendingPath -Force -ErrorAction SilentlyContinue
    }

    if (Test-Path -LiteralPath $OutputPath) {
        Remove-Item -LiteralPath $OutputPath -Force -ErrorAction SilentlyContinue
    }

    $processOutput = $text | & $PiperExePath `
        --model $ModelPath `
        --config $ConfigPath `
        --espeak_data $EspeakDataPath `
        --output_file $OutputPath 2>&1 | Out-String

    if ($LASTEXITCODE -ne 0) {
        throw ('Piper failed with exit code {0}. {1}' -f $LASTEXITCODE, $processOutput.Trim())
    }

    if (-not (Test-Path -LiteralPath $OutputPath)) {
        throw 'Piper finished without producing a WAV file.'
    }

    Write-Status -State 'done' -Message 'Audio is ready.'
    exit 0
} catch {
    Write-Status -State 'failed' -Message 'The Piper engine could not finish.' -ErrorMessage $_.Exception.Message
    exit 1
}
