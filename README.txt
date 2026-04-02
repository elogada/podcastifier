Podcastifier v1
================

What it does
-------------
- Runs locally on Windows through XAMPP / PHP 8.2+
- Uses local Windows speech voices
- Accepts pasted text or a DOCX upload
- Generates runtime/output.wav
- Lets the user play or download the WAV from the browser

Quick install
-------------
1. Install XAMPP for Windows.
2. Start Apache from the XAMPP Control Panel.
3. Extract this folder into: xampp/htdocs/podcastifier
4. Open: http://localhost/podcastifier/
5. Let the setup checklist finish.

Notes
-----
- PowerShell is required.
- At least one local Windows speech voice must be installed.
- DOCX upload depends on the PHP ZIP extension.
- The app is designed for one local user and one generation task at a time.

Files
-----
- index.php          Main app page
- check.php          Setup checklist endpoint
- generate.php       Starts TTS generation
- status.php         Returns current status
- stop.php           Stops the running TTS process
- common.php         Shared helpers
- tts/windows_tts.ps1  Local Windows TTS helper
- assets/app.css     Local styles
- assets/app.js      Frontend logic
- runtime/           Generated files and status data
- uploads/           Placeholder upload folder

Common issues
-------------
- If the checklist says no voices were detected, install or enable a Windows speech voice.
- If DOCX uploads fail, confirm the ZIP extension is enabled in PHP.
- If generation does not start, make sure PowerShell is available and not blocked by local policy.
