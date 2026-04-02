param(
  [string]$Input,
  [string]$Output,
  [string]$Pending,
  [string]$StatusFile,
  [string]$Voice,
  [int]$Rate = 0
)
Write-Output ("Input=<{0}> Output=<{1}> Pending=<{2}> StatusFile=<{3}> Voice=<{4}> Rate=<{5}>" -f $Input, $Output, $Pending, $StatusFile, $Voice, $Rate)
