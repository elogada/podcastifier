# Podcastifier

Podcastifier is a local-first Windows web app for turning pasted text or DOCX content into WAV audio through XAMPP and PHP. It is meant for local use by students, researchers, and other solo users who want a setup that stays as simple as possible.

## What It Does

- Runs locally on Windows through XAMPP and PHP 8.2+
- Downloads the Piper runtime on first setup
- Installs Piper voices on demand from the app
- Accepts pasted text or a DOCX upload
- Generates `runtime/output.wav`
- Lets the user play or download the WAV in the browser

## Intended Use

- Podcastifier is designed for one local user at a time.
- It is not designed for multiple concurrent users or shared hosting.
- It is meant for light local workloads, not sustained server-style use.
- Very large inputs, repeated back-to-back generations, or unusually large uploaded files may slow the app down or cause failures.

## Installation

1. Install XAMPP for Windows.
2. Start Apache from the XAMPP Control Panel.
3. Extract this project into `xampp/htdocs/podcastifier`.
4. Open `http://localhost/podcastifier/` or your local vhost URL.
5. On the setup screen, install the default Piper voice.
6. Generate audio locally in the browser.

## First-Run Setup

On first run, Podcastifier downloads:

- The pinned Piper Windows runtime
- The default Piper voice if you choose to install it

Extra voices can be installed later from the in-app voice library.

The pinned Piper runtime URL is stored in `app_config.php`. If you ever need to update the upstream release URL, that is the main file to edit.

## Requirements

- Windows
- XAMPP / Apache
- PHP 8.2+
- Internet access the first time Piper runtime or voices are downloaded

## DOCX Support

DOCX upload is supported through a ZIP-based parser.

- If `ZipArchive` is available, PHP can use it directly.
- If `ZipArchive` is unavailable, Podcastifier includes a pure-PHP fallback for reading DOCX and ZIP archives.

## Voices

Current built-in voice options:

- `English (United States) - Joe` as the default setup voice
- `English (Great Britain) - Cori` as an optional install
- `English (Great Britain) - Alan` as an optional install

## Project Notes

- Podcastifier is intentionally local-first and single-user.
- Runtime files are generated under `runtime/`.
- Installed Piper files are also stored under `runtime/`.
- The app favors easy installation over multi-user scalability.

## License

This project uses the MIT License in `LICENSE`.

That means the software is provided "as is", without warranty. In practical terms:

- Misuse, unsupported modifications, or overloading the app with very large files may cause crashes or failed generations.
- The project does not guarantee suitability for production hosting, shared access, or heavy concurrent workloads.
- You are responsible for reviewing and testing any local changes you make.

## Files

- `index.php`: Main app page
- `check.php`: Setup checklist endpoint
- `generate.php`: Runs Piper generation
- `status.php`: Returns current status
- `install_piper.php`: Installs Piper runtime and voices
- `voices.php`: Returns installed and available Piper voices
- `common.php`: Shared helpers
- `piper.php`: Piper download and voice helpers
- `zip_utils.php`: Pure-PHP ZIP helpers
- `app_config.php`: App configuration, including the pinned Piper runtime URL
- `assets/app.js`: Frontend logic
- `assets/app.css`: Local styles
- `runtime/.gitkeep`: Runtime folder placeholder
- `uploads/.gitkeep`: Upload folder placeholder
