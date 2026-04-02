param(
  [string]$InputPath,
  [string]$Pending,
  [string]$Output,
  [string]$StatusFile,
  [string]$Voice,
  [int]$Rate = 0
)
Write-Output ("InputPath=<{0}> Pending=<{1}> Output=<{2}> StatusFile=<{3}> Voice=<{4}> Rate=<{5}>" -f $InputPath, $Pending, $Output, $StatusFile, $Voice, $Rate)
