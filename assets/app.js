const setupSplash = document.getElementById('setupSplash');
const checkList = document.getElementById('checkList');
const checkFooter = document.getElementById('checkFooter');
const retryCheckBtn = document.getElementById('retryCheckBtn');
const installDefaultBtn = document.getElementById('installDefaultBtn');
const appRoot = document.getElementById('appRoot');
const generatorForm = document.getElementById('generatorForm');
const voiceSelect = document.getElementById('voiceSelect');
const rateInput = document.getElementById('rateInput');
const rateValue = document.getElementById('rateValue');
const textInput = document.getElementById('textInput');
const docxInput = document.getElementById('docxInput');
const usedTextPreview = document.getElementById('usedTextPreview');
const generateBtn = document.getElementById('generateBtn');
const stopBtn = document.getElementById('stopBtn');
const statusText = document.getElementById('statusText');
const liveStatusBadge = document.getElementById('liveStatusBadge');
const audioPlayer = document.getElementById('audioPlayer');
const downloadLink = document.getElementById('downloadLink');
const refreshVoicesBtn = document.getElementById('refreshVoicesBtn');
const voiceCatalog = document.getElementById('voiceCatalog');

let pollTimer = null;
let setupOk = false;
let defaultVoiceId = '';
let voiceCatalogData = [];

function setStatus(message, tone = 'neutral') {
  statusText.textContent = message;
  liveStatusBadge.textContent = tone === 'busy' ? 'Working' : tone === 'error' ? 'Issue' : tone === 'done' ? 'Done' : 'Ready';
  liveStatusBadge.dataset.tone = tone;
}

function renderChecks(data) {
  checkList.innerHTML = '';
  voiceCatalogData = data.catalog || [];
  defaultVoiceId = data.default_voice_id || '';

  data.items.forEach((item) => {
    const row = document.createElement('div');
    row.className = 'check-row';

    const icon = document.createElement('span');
    icon.className = 'check-icon';
    icon.textContent = item.ok ? '✅' : '❌';

    const content = document.createElement('div');
    content.className = 'check-copy';

    const title = document.createElement('div');
    title.className = 'check-label';
    title.textContent = item.label;

    const hint = document.createElement('div');
    hint.className = 'check-hint';
    hint.textContent = item.hint || '';

    content.appendChild(title);
    content.appendChild(hint);
    row.appendChild(icon);
    row.appendChild(content);
    checkList.appendChild(row);
  });

  populateVoices(data.voices || []);
  renderVoiceCatalog(voiceCatalogData);

  const defaultVoiceInstalled = (data.voices || []).some((voice) => voice.id === defaultVoiceId);
  const canInstallDefault = !!data.downloads_ready && !defaultVoiceInstalled;
  installDefaultBtn?.classList.toggle('hidden', !canInstallDefault);

  if (data.ok) {
    checkFooter.textContent = 'All systems ready.';
    setupOk = true;
    setupSplash.classList.add('hidden');
    appRoot.classList.remove('hidden');
    appRoot.setAttribute('aria-hidden', 'false');
  } else {
    checkFooter.textContent = canInstallDefault
      ? 'Install the default Piper voice to finish setup, then retry.'
      : 'Setup incomplete. Fix the failed items, then retry.';
    setupOk = false;
    setupSplash.classList.remove('hidden');
    appRoot.classList.add('hidden');
    appRoot.setAttribute('aria-hidden', 'true');
  }
}

function populateVoices(voices) {
  voiceSelect.innerHTML = '';
  if (!voices.length) {
    const option = document.createElement('option');
    option.value = '';
    option.textContent = 'No Piper voices installed';
    voiceSelect.appendChild(option);
    return;
  }

  voices.forEach((voice) => {
    const option = document.createElement('option');
    option.value = voice.id;
    option.textContent = voice.label;
    voiceSelect.appendChild(option);
  });
}

function renderVoiceCatalog(catalog) {
  if (!voiceCatalog) {
    return;
  }

  voiceCatalog.innerHTML = '';
  if (!catalog.length) {
    const empty = document.createElement('p');
    empty.className = 'subtle';
    empty.textContent = 'No voice catalog available.';
    voiceCatalog.appendChild(empty);
    return;
  }

  catalog.forEach((voice) => {
    const row = document.createElement('div');
    row.className = 'voice-row';

    const copy = document.createElement('div');
    copy.className = 'voice-copy';

    const title = document.createElement('div');
    title.className = 'voice-title';
    title.textContent = voice.label;

    if (voice.default) {
      const badge = document.createElement('span');
      badge.className = 'voice-badge';
      badge.textContent = 'Default';
      title.appendChild(document.createTextNode(' '));
      title.appendChild(badge);
    }

    const meta = document.createElement('div');
    meta.className = 'voice-meta';
    meta.textContent = `${voice.description} ${voice.size_label ? `Approx. ${voice.size_label}.` : ''}`;

    copy.appendChild(title);
    copy.appendChild(meta);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = voice.installed ? 'btn btn-secondary' : 'btn btn-primary';
    button.textContent = voice.installed ? 'Installed' : 'Install';
    button.disabled = voice.installed;
    button.dataset.voiceId = voice.id;
    button.addEventListener('click', () => installVoice(voice.id, button));

    row.appendChild(copy);
    row.appendChild(button);
    voiceCatalog.appendChild(row);
  });
}

async function fetchVoiceCatalog() {
  const response = await fetch('voices.php', { cache: 'no-store' });
  const data = await response.json();
  if (!response.ok || !data.ok) {
    throw new Error(data.message || 'Could not refresh the voice catalog.');
  }

  voiceCatalogData = data.catalog || [];
  defaultVoiceId = data.default_voice_id || defaultVoiceId;
  populateVoices(data.voices || []);
  renderVoiceCatalog(voiceCatalogData);
}

async function installVoice(voiceId, button = null) {
  const formData = new FormData();
  formData.append('voice_id', voiceId);

  if (button) {
    button.disabled = true;
    button.textContent = 'Installing...';
  }

  generateBtn.disabled = true;
  setStatus('Installing Piper files...', 'busy');
  checkFooter.textContent = 'Downloading Piper runtime and voice files...';

  try {
    const response = await fetch('install_piper.php', {
      method: 'POST',
      body: formData,
    });
    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.message || 'Install failed.');
    }

    setStatus(data.message || 'Voice installed.', 'done');
    await runCheck();
    if (setupOk) {
      await fetchVoiceCatalog();
    }
  } catch (error) {
    setStatus(error.message || 'Could not install Piper files.', 'error');
    if (button) {
      button.disabled = false;
      button.textContent = 'Install';
    }
  } finally {
    generateBtn.disabled = false;
  }
}

async function runCheck() {
  checkFooter.textContent = 'Running checks...';
  try {
    const response = await fetch('check.php', { cache: 'no-store' });
    const data = await response.json();
    renderChecks(data);
  } catch (error) {
    checkList.innerHTML = '';
    checkFooter.textContent = 'Could not run checks. Make sure Apache is running and try again.';
  }
}

async function pollStatus() {
  try {
    const response = await fetch('status.php', { cache: 'no-store' });
    const data = await response.json();

    if (data.state === 'processing') {
      setStatus(data.message || 'Generating audio...', 'busy');
      generateBtn.disabled = true;
      stopBtn.disabled = false;
    } else if (data.state === 'done') {
      setStatus(data.message || 'Audio is ready.', 'done');
      generateBtn.disabled = false;
      stopBtn.disabled = true;
      if (data.audio_url) {
        audioPlayer.hidden = false;
        audioPlayer.src = data.audio_url;
        downloadLink.classList.remove('hidden');
        downloadLink.href = data.audio_url;
      }
      clearInterval(pollTimer);
      pollTimer = null;
    } else if (data.state === 'failed' || data.state === 'stopped') {
      setStatus(data.error || data.message || 'Generation stopped.', 'error');
      generateBtn.disabled = false;
      stopBtn.disabled = true;
      clearInterval(pollTimer);
      pollTimer = null;
    } else {
      setStatus(data.message || 'Ready.');
      generateBtn.disabled = false;
      stopBtn.disabled = true;
    }
  } catch (error) {
    setStatus('Could not fetch status.', 'error');
    generateBtn.disabled = false;
    stopBtn.disabled = true;
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

retryCheckBtn?.addEventListener('click', runCheck);
installDefaultBtn?.addEventListener('click', () => installVoice(defaultVoiceId || 'en_US-joe-medium', installDefaultBtn));
refreshVoicesBtn?.addEventListener('click', async () => {
  try {
    await fetchVoiceCatalog();
    setStatus('Voice list refreshed.');
  } catch (error) {
    setStatus(error.message || 'Could not refresh voices.', 'error');
  }
});
rateInput?.addEventListener('input', () => {
  rateValue.textContent = rateInput.value;
});

docxInput?.addEventListener('change', () => {
  if (docxInput.files && docxInput.files.length > 0) {
    usedTextPreview.value = `DOCX selected: ${docxInput.files[0].name}\n\nThe extracted text will appear here after generation starts.`;
  }
});

generatorForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!setupOk) {
    return;
  }

  const formData = new FormData(generatorForm);
  generateBtn.disabled = true;
  stopBtn.disabled = true;
  setStatus('Starting generation...', 'busy');
  audioPlayer.hidden = true;
  audioPlayer.removeAttribute('src');
  downloadLink.classList.add('hidden');

  try {
    const response = await fetch('generate.php', {
      method: 'POST',
      body: formData,
    });
    const data = await response.json();

    if (!response.ok || !data.ok) {
      throw new Error(data.message || 'Generation failed.');
    }

    if (data.used_text) {
      usedTextPreview.value = data.used_text;
      if (!textInput.value.trim()) {
        textInput.value = data.used_text;
      }
    }

    if (data.audio_url) {
      audioPlayer.hidden = false;
      audioPlayer.src = data.audio_url;
      downloadLink.classList.remove('hidden');
      downloadLink.href = data.audio_url;
    }
    setStatus(data.message || 'Audio is ready.', 'done');
    generateBtn.disabled = false;
  } catch (error) {
    setStatus(error.message || 'Generation failed.', 'error');
    generateBtn.disabled = false;
    stopBtn.disabled = true;
  }
});

stopBtn?.addEventListener('click', async () => {
  stopBtn.disabled = true;
  try {
    const response = await fetch('stop.php', { method: 'POST' });
    const data = await response.json();
    setStatus(data.message || 'Generation stopped.', 'error');
  } catch (error) {
    setStatus('Could not stop the generation process.', 'error');
  } finally {
    generateBtn.disabled = false;
    stopBtn.disabled = true;
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }
});

runCheck();
pollStatus();
