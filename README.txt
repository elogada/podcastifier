Podcastifier v1
================

What it does
-------------
- Runs locally on Windows through XAMPP / PHP 8.2+
- Uses local Piper voices
- Accepts pasted text or a DOCX upload
- Generates runtime/output.wav
- Lets the user play or download the WAV from the browser

Quick install
-------------
1. Install XAMPP for Windows.
2. Start Apache from the XAMPP Control Panel.
3. Extract this folder into: xampp/htdocs/podcastifier
4. Open: http://localhost/podcastifier/
5. Use the setup screen to install the default Piper voice.
6. Generate audio locally in the browser.

Notes
-----
- PowerShell is used internally for the local Piper worker.
- On first run, Podcastifier downloads the Piper runtime and at least one voice.
- An internet connection is required the first time you install Piper voices.
- DOCX upload depends on the PHP ZIP extension.
- The app is designed for one local user and one generation task at a time.

Files
-----
- index.php          Main app page
- check.php          Setup checklist endpoint
- generate.php       Runs Piper generation
- status.php         Returns current status
- stop.php           Stops the running TTS process
- common.php         Shared helpers
- piper.php          Piper download and voice helpers
- install_piper.php  Installs Piper runtime and voices
- voices.php         Returns installed and available Piper voices
- tts/piper_worker.ps1  Local Piper synthesis helper
- assets/app.css     Local styles
- assets/app.js      Frontend logic
- runtime/           Generated files and status data
- uploads/           Placeholder upload folder

Common issues
-------------
- If the checklist says Piper is not installed, use the setup button to install the default voice.
- If extra voices are missing, install them from the in-app voice library panel.
- If DOCX uploads fail, confirm the ZIP extension is enabled in PHP.
- If generation does not start, make sure PowerShell is available and that PHP can download remote files.
