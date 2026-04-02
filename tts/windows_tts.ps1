param(
    [Parameter(Position = 0)]
    [string]$Action = 'list',
    [string]$Input,
    [string]$Pending,
    [string]$Output,
    [string]$StatusFile,
    [string]$Voice,
    [int]$Rate = 0
)

function Write-Status {
    param(
        [string]$State,
        [string]$Message,
        [string]$ErrorMessage = ''
    )

    if ([string]::IsNullOrWhiteSpace($StatusFile)) {
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
    Set-Content -LiteralPath $StatusFile -Value $json -Encoding UTF8
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
            if (-not (Test-Path -LiteralPath $Input)) {
                throw 'Input text file was not found.'
            }

            $text = Get-Content -LiteralPath $Input -Raw -Encoding UTF8
            if ([string]::IsNullOrWhiteSpace($text)) {
                throw 'Input text is empty.'
            }

            $directory = Split-Path -Parent $Output
            if (-not (Test-Path -LiteralPath $directory)) {
                New-Item -ItemType Directory -Path $directory -Force | Out-Null
            }

            if ([string]::IsNullOrWhiteSpace($Pending)) {
                $Pending = $Output
            }

            $synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
            if (-not [string]::IsNullOrWhiteSpace($Voice)) {
                $synth.SelectVoice($Voice)
            }
            $synth.Rate = $Rate

            Write-Status -State 'processing' -Message 'Generating audio...'

            if (Test-Path -LiteralPath $Pending) {
                Remove-Item -LiteralPath $Pending -Force -ErrorAction SilentlyContinue
            }
            if (Test-Path -LiteralPath $Output) {
                Remove-Item -LiteralPath $Output -Force -ErrorAction SilentlyContinue
            }

            $synth.SetOutputToWaveFile($Pending)
            $synth.Speak($text)
            $synth.SetOutputToNull()
            $synth.Dispose()

            Move-Item -LiteralPath $Pending -Destination $Output -Force
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
