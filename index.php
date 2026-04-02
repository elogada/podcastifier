<?php

declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podcastifier</title>
    <link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>
<div class="page-shell">
    <div id="setupSplash" class="splash-card">
        <div class="brand-row">
            <div>
                <p class="eyebrow">Local-first audio tool</p>
                <h1>Podcastifier</h1>
                <p class="subtle">Quick setup check before the app goes live.</p>
            </div>
            <div class="button-row compact-row">
                <button id="installDefaultBtn" class="btn btn-primary hidden" type="button">Install Default Voice</button>
                <button id="retryCheckBtn" class="btn btn-secondary" type="button">Retry check</button>
            </div>
        </div>

        <div id="checkList" class="check-list"></div>
        <div id="checkFooter" class="check-footer muted">Running checks...</div>
    </div>

    <main id="appRoot" class="app-card hidden" aria-hidden="true">
        <div class="header-row">
            <div>
                <p class="eyebrow">Local Piper TTS</p>
                <h1>Podcastifier</h1>
                <p class="subtle">Paste text or upload a DOCX, then turn it into a local WAV file with Piper.</p>
            </div>
            <div class="status-badge" id="liveStatusBadge">Ready</div>
        </div>

        <form id="generatorForm" class="stack-lg" enctype="multipart/form-data">
            <section class="panel">
                <div class="field-grid two-up">
                    <label class="field">
                        <span>Voice</span>
                        <select id="voiceSelect" name="voice"></select>
                    </label>

                    <label class="field">
                        <span>Rate</span>
                        <input type="range" id="rateInput" name="rate" min="-10" max="10" value="0">
                        <small id="rateValue">0</small>
                    </label>
                </div>

                <label class="field">
                    <span>Paste your text</span>
                    <textarea id="textInput" name="text" rows="14" placeholder="Paste notes, chapters, or research text here..."></textarea>
                </label>

                <label class="field upload-box">
                    <span>Or upload a DOCX file</span>
                    <input type="file" id="docxInput" name="docx" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    <small>DOCX text will be extracted and used for the audio.</small>
                </label>
            </section>

            <section class="panel actions-panel">
                <div class="button-row">
                    <button id="generateBtn" class="btn btn-primary" type="submit">Generate WAV</button>
                    <button id="stopBtn" class="btn btn-secondary" type="button" disabled>Stop</button>
                </div>
                <p id="statusText" class="status-text">Ready.</p>
            </section>

            <section class="panel">
                <label class="field">
                    <span>Preview / extracted text</span>
                    <textarea id="usedTextPreview" rows="10" readonly placeholder="When you upload a DOCX or start a generation, the exact text used will appear here."></textarea>
                </label>
            </section>

            <section class="panel">
                <div class="audio-row">
                    <div>
                        <h2>Voice Library</h2>
                        <p class="subtle">Install extra Piper voices on demand.</p>
                    </div>
                    <button id="refreshVoicesBtn" class="btn btn-secondary" type="button">Refresh voices</button>
                </div>
                <div id="voiceCatalog" class="voice-catalog"></div>
            </section>

            <section class="panel">
                <div class="audio-row">
                    <div>
                        <h2>Playback</h2>
                        <p class="subtle">The player becomes available when the WAV file is ready.</p>
                    </div>
                    <a id="downloadLink" class="btn btn-secondary hidden" href="runtime/output.wav" download>Download WAV</a>
                </div>
                <audio id="audioPlayer" controls preload="none" class="audio-player" hidden></audio>
            </section>
        </form>
    </main>
</div>
<script src="assets/app.js?v=1"></script>
</body>
</html>
