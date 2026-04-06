# Podcastifier

## Why Did I make This

I get documents a lot at work. And I play a lot of Old School Runescape. Code switching is hard, tabbing out is hard, even harder when you have to focus on catching fish. Ergo I made Podcastifier. With this I can listen to documents being read to me by an AI while doing my fishing loops. No more multi-monitor. No more alt-tabs. No more interuptions during work. This is the future of AI augmentation: *AI assistance, for reading, for play, and for work - concurrently* .

This was designed to be installed with as little installation steps as possible so that students, researchers, and non-techie people can make use of it with as little interaction in the backend as possible.

## What It Does

It uses a *neural (Artificial Intelligence-based) text-to-speech* system called *Piper* that runs locally, inside your PC. No need to be online except on the first run when you download the Piper runtime (the "AI player") and the Piper voices.

_Side note: Did you know that you can train Piper to use your own voice? See the docs here: https://github.com/rhasspy/piper/blob/master/TRAINING.md_

## Installation

1. Get *XAMPP 8.2* from the official ApacheFriends website and install Apache. - https://www.apachefriends.org/
2. Get the latest Podcastifier ZIP from the Releases page. https://github.com/elogada/podcastifier/releases/tag/release
3. Paste the ZIP's contents at `xampp/htdocs/podcastifier`
4. Via the XAMPP control panel, `Start` the Apache service
5. On your browser (Chrome, Edge, Opera, whatever), open `http://localhost/podcastifier/` 
6. Finish the first check and let it install the voice files. Serve hot.

## Intended Use

- Podcastifier is designed for *one local user* at a time. It is **not** designed for multiple users.
- Very large workloads or unusually large uploaded files may fail.

## Requirements

- *Windows:* Extensively tested on Windows 11 Home and Windows 11 Pro
- *Apache* via XAMPP 8.2
- *PHP 8.2+* via XAMPP 8.2
- *Internet access* the first time Piper runtime or voices are downloaded

## Voices

Built-in voice options:

- `English (United States) - Joe` as the default setup voice
- `English (Great Britain) - Cori` as an optional install
- `English (Great Britain) - Alan` as an optional install
- `English (United States) - Kristin` as an optional install

## License

This project uses the MIT License in `LICENSE`.

That means the software is provided "as is", without warranty. In practical terms:

- Misuse, unsupported modifications, or overloading the app with very large files may cause crashes or failed generations.
- The project does not guarantee suitability for production hosting, shared access, or heavy concurrent workloads.
- You are responsible for reviewing and testing any local changes you make.
