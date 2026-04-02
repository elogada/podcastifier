param(
    [Parameter(Position = 0)]
    [string]$Action = 'list',
    [string]$InputPath,
    [string]$PendingPath,
    [string]$OutputPath,
    [string]$StatusFilePath,
    [string]$Voice,
    [int]$Rate = 0
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
        voice      = $Voice
        updated_at = (Get-Date).ToString('o')
        error      = $(if ([string]::IsNullOrWhiteSpace($ErrorMessage)) { $null } else { $ErrorMessage })
    }

    $json = $payload | ConvertTo-Json -Depth 4
    Set-Content -LiteralPath $StatusFilePath -Value $json -Encoding UTF8
}

try {
    Add-Type -AssemblyName System.Speech
} catch {
    Write-Error 'System.Speech is not available on this machine.'
    exit 1
}

switch ($Action.ToLower()) {
    'list' {
        try {
            $synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
            $synth.GetInstalledVoices() | ForEach-Object {
                $_.VoiceInfo.Name
            }
            $synth.Dispose()
            exit 0
        } catch {
            Write-Error $_.Exception.Message
            exit 1
        }
    }

    'synth' {
        try {
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

            if ([string]::IsNullOrWhiteSpace($PendingPath)) {
                $PendingPath = $OutputPath
            }

            $synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
            if (-not [string]::IsNullOrWhiteSpace($Voice)) {
                $synth.SelectVoice($Voice)
            }
            $synth.Rate = $Rate

            Write-Status -State 'processing' -Message 'Generating audio...'

            if (Test-Path -LiteralPath $PendingPath) {
                Remove-Item -LiteralPath $PendingPath -Force -ErrorAction SilentlyContinue
            }
            if (Test-Path -LiteralPath $OutputPath) {
                Remove-Item -LiteralPath $OutputPath -Force -ErrorAction SilentlyContinue
            }

            $synth.SetOutputToWaveFile($PendingPath)
            $synth.Speak($text)
            $synth.SetOutputToNull()
            $synth.Dispose()

            Move-Item -LiteralPath $PendingPath -Destination $OutputPath -Force
            Write-Status -State 'done' -Message 'Audio is ready.'
            exit 0
        } catch {
            Write-Status -State 'failed' -Message 'The speech engine could not finish.' -ErrorMessage $_.Exception.Message
            try {
                if ($synth) {
                    $synth.SetOutputToNull()
                    $synth.Dispose()
                }
            } catch { }
            exit 1
        }
    }

    default {
        Write-Error 'Unknown action. Use list or synth.'
        exit 1
    }
}
