(() => {
  // Public API (called from inline onclick attributes)
  window.matchCertKey = matchCertKey;
  window.matchCertPublic = matchCertPublic;
  window.matchCerts = matchCerts;
  window.matchPubPriv = matchPubPriv;
  window.clearAll = clearAll;
  window.copyResults = copyResults;
  window.copyToClipboard = window.copyToClipboard || copyToClipboard;

  // Tab switching
  function initTabs() {
    document.querySelectorAll('.match-tab').forEach(tab => {
      tab.addEventListener('click', function () {
        document.querySelectorAll('.match-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const tabId = this.dataset.tab;
        document.querySelectorAll('.panel').forEach(panel => panel.classList.remove('active'));

        const panel = document.getElementById(`panel-${tabId}`);
        if (panel) panel.classList.add('active');

        const resultsSection = document.getElementById('resultsSection');
        if (resultsSection) resultsSection.style.display = 'none';
      });
    });
  }

  // File upload handlers
  function setupFileHandler(fileInputId, textareaId) {
    const fileInput = document.getElementById(fileInputId);
    const textarea = document.getElementById(textareaId);
    if (!fileInput || !textarea) return;

    fileInput.addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function (event) {
        textarea.value = event.target.result;
      };
      reader.readAsText(file);
    });
  }

  function clearFile(fileInputId, textareaId) {
    const fi = document.getElementById(fileInputId);
    const ta = document.getElementById(textareaId);
    if (fi) fi.value = '';
    if (ta) ta.value = '';
  }
  window.clearFile = clearFile;

  // Helpers
  function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = show ? 'flex' : 'none';
  }

  function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i> ${message}`;

    toast.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: ${type === 'success' ? '#10b981' : '#ef4444'};
      color: white;
      padding: 12px 20px;
      border-radius: 10px;
      z-index: 10002;
      animation: slideInRight 0.3s ease;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    `;

    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  }

  function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
    showToast('Command copied to clipboard!', 'success');
  }

  function copyResults() {
    const matchMessage = document.getElementById('matchMessage')?.innerText || '';
    const details = document.getElementById('matchDetails')?.innerText || '';
    const textToCopy = `${matchMessage}\n\n${details}`.trim();

    navigator.clipboard.writeText(textToCopy);
    showToast('Results copied to clipboard!', 'success');
  }

  // Results rendering
  function displayResults(data) {
    const matchBadge = document.getElementById('matchBadge');
    const matchMessage = document.getElementById('matchMessage');
    const matchDetails = document.getElementById('matchDetails');

    if (!matchBadge || !matchMessage || !matchDetails) return;

    matchBadge.className = `match-badge ${data.match ? 'match-success' : 'match-fail'}`;
    matchBadge.innerHTML = data.match
      ? '<i class="bi bi-check-circle-fill me-2"></i> MATCH'
      : '<i class="bi bi-x-circle-fill me-2"></i> NO MATCH';

    matchMessage.innerHTML = data.message || '';

    let detailsHtml = '';

    if (data.certificate) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-file-earmark-lock-fill"></i> Certificate Details</h4>
          <div class="detail-row"><span class="detail-label">Subject:</span><span class="detail-value">${escapeHtml(data.certificate.subject)}</span></div>
          <div class="detail-row"><span class="detail-label">Issuer:</span><span class="detail-value">${escapeHtml(data.certificate.issuer)}</span></div>
          <div class="detail-row"><span class="detail-label">Serial Number:</span><span class="detail-value">${escapeHtml(data.certificate.serial_number)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid From:</span><span class="detail-value">${escapeHtml(data.certificate.valid_from)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid To:</span><span class="detail-value">${escapeHtml(data.certificate.valid_to)}</span></div>
          <div class="detail-row"><span class="detail-label">Signature Algorithm:</span><span class="detail-value">${escapeHtml(data.certificate.signature_algorithm)}</span></div>
          <div class="detail-row"><span class="detail-label">Public Key Algorithm:</span><span class="detail-value">${escapeHtml(data.certificate.public_key_algorithm)}</span></div>
          <div class="detail-row"><span class="detail-label">Version:</span><span class="detail-value">${escapeHtml(data.certificate.version)}</span></div>
          <div class="detail-row"><span class="detail-label">MD5 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.certificate.fingerprint_md5)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA1 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.certificate.fingerprint_sha1)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA256 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.certificate.fingerprint_sha256)}</span></div>
        </div>
      `;
    }

    if (data.private_key) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-key-fill"></i> Private Key Details</h4>
          <div class="detail-row"><span class="detail-label">Type:</span><span class="detail-value">${escapeHtml(data.private_key.type)}</span></div>
          <div class="detail-row"><span class="detail-label">Key Size:</span><span class="detail-value">${escapeHtml(data.private_key.key_size)} bits</span></div>
          <div class="detail-row"><span class="detail-label">Encrypted:</span><span class="detail-value">${data.private_key.is_encrypted ? 'Yes' : 'No'}</span></div>
          <div class="detail-row"><span class="detail-label">MD5 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.private_key.fingerprint_md5)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA1 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.private_key.fingerprint_sha1)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA256 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.private_key.fingerprint_sha256)}</span></div>
        </div>
      `;
    }

    if (data.public_key) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-unlock-fill"></i> Public Key Details</h4>
          <div class="detail-row"><span class="detail-label">Type:</span><span class="detail-value">${escapeHtml(data.public_key.type)}</span></div>
          <div class="detail-row"><span class="detail-label">Key Size:</span><span class="detail-value">${escapeHtml(data.public_key.key_size)} bits</span></div>
          <div class="detail-row"><span class="detail-label">MD5 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.public_key.fingerprint_md5)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA1 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.public_key.fingerprint_sha1)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA256 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.public_key.fingerprint_sha256)}</span></div>
        </div>
      `;
    }

    if (data.certificate1 && data.certificate2) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-file-earmark-lock-fill"></i> Certificate 1</h4>
          <div class="detail-row"><span class="detail-label">Subject:</span><span class="detail-value">${escapeHtml(data.certificate1.subject)}</span></div>
          <div class="detail-row"><span class="detail-label">Serial Number:</span><span class="detail-value">${escapeHtml(data.certificate1.serial_number)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid From:</span><span class="detail-value">${escapeHtml(data.certificate1.valid_from)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid To:</span><span class="detail-value">${escapeHtml(data.certificate1.valid_to)}</span></div>
        </div>
        <div class="detail-card">
          <h4><i class="bi bi-file-earmark-lock-fill"></i> Certificate 2</h4>
          <div class="detail-row"><span class="detail-label">Subject:</span><span class="detail-value">${escapeHtml(data.certificate2.subject)}</span></div>
          <div class="detail-row"><span class="detail-label">Serial Number:</span><span class="detail-value">${escapeHtml(data.certificate2.serial_number)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid From:</span><span class="detail-value">${escapeHtml(data.certificate2.valid_from)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid To:</span><span class="detail-value">${escapeHtml(data.certificate2.valid_to)}</span></div>
        </div>
      `;
    }

    if (data.cert_modulus_hash) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-diagram-3"></i> Modulus Comparison</h4>
          <div class="detail-row"><span class="detail-label">Certificate Modulus:</span><span class="fingerprint">${escapeHtml(data.cert_modulus_hash)}</span></div>
          <div class="detail-row"><span class="detail-label">Key Modulus:</span><span class="fingerprint">${escapeHtml(data.key_modulus_hash || data.pub_modulus_hash)}</span></div>
        </div>
      `;
    }

    // For Public Key + Private Key we will have pub_modulus_hash and key_modulus_hash
    if (data.pub_modulus_hash || data.key_modulus_hash) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-diagram-3"></i> Modulus Comparison</h4>
          <div class="detail-row"><span class="detail-label">Public Key Modulus:</span><span class="fingerprint">${escapeHtml(data.pub_modulus_hash)}</span></div>
          <div class="detail-row"><span class="detail-label">Private Key Modulus:</span><span class="fingerprint">${escapeHtml(data.key_modulus_hash)}</span></div>
        </div>
      `;
    }

    matchDetails.innerHTML = detailsHtml;
    const resultsSection = document.getElementById('resultsSection');
    if (resultsSection) {
      resultsSection.style.display = 'block';
      resultsSection.scrollIntoView({ behavior: 'smooth' });
    }
  }

  // Global config
  const csrfToken = window.SSL_MATCHER_CSRF_TOKEN || '';

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });
    return response.json();
  }

  async function matchCertKey() {
    const certificate = document.getElementById('certContent').value;
    const privateKey = document.getElementById('keyContent').value;
    const keyPassword = document.getElementById('keyPassword').value;

    if (!certificate && !privateKey) return showToast('Please provide certificate and private key', 'error');
    if (!certificate) return showToast('Please provide certificate content or upload file', 'error');
    if (!privateKey) return showToast('Please provide private key content or upload file', 'error');

    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/match-cert-key', {
        certificate,
        private_key: privateKey,
        key_password: keyPassword
      });

      if (data.success) displayResults(data);
      else showToast(data.message || 'Failed to match', 'error');
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  async function matchCertPublic() {
    const certificate = document.getElementById('certContent2').value;
    const publicKey = document.getElementById('pubContent').value;

    if (!certificate && !publicKey) return showToast('Please provide certificate and public key', 'error');
    if (!certificate) return showToast('Please provide certificate content or upload file', 'error');
    if (!publicKey) return showToast('Please provide public key content or upload file', 'error');

    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/match-cert-public', {
        certificate,
        public_key: publicKey
      });

      if (data.success) displayResults(data);
      else showToast(data.message || 'Failed to match', 'error');
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  async function matchCerts() {
    const certificate1 = document.getElementById('cert1Content').value;
    const certificate2 = document.getElementById('cert2Content').value;

    if (!certificate1 && !certificate2) return showToast('Please provide both certificates', 'error');
    if (!certificate1) return showToast('Please provide certificate 1', 'error');
    if (!certificate2) return showToast('Please provide certificate 2', 'error');

    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/match-certs', {
        certificate1,
        certificate2
      });

      if (data.success) displayResults(data);
      else showToast(data.message || 'Failed to compare certificates', 'error');
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  async function matchPubPriv() {
    const publicKey = document.getElementById('pubContent2').value;
    const privateKey = document.getElementById('keyContent2').value;
    const keyPassword = document.getElementById('keyPassword2').value;

    if (!publicKey && !privateKey) return showToast('Please provide public key and private key', 'error');
    if (!publicKey) return showToast('Please provide public key content or upload file', 'error');
    if (!privateKey) return showToast('Please provide private key content or upload file', 'error');

    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/match-pub-priv', {
        public_key: publicKey,
        private_key: privateKey,
        key_password: keyPassword
      });

      if (data.success) displayResults(data);
      else showToast(data.message || 'Failed to match', 'error');
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  function clearAll() {
    const textareas = ['certContent', 'keyContent', 'certContent2', 'pubContent', 'cert1Content', 'cert2Content', 'pubContent2', 'keyContent2'];
    textareas.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });

    const fileInputs = ['certFile', 'keyFile', 'certFile2', 'pubFile', 'cert1File', 'cert2File', 'pubFile2', 'keyFile2'];
    fileInputs.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });

    const pwd = document.getElementById('keyPassword');
    if (pwd) pwd.value = '';
    const pwd2 = document.getElementById('keyPassword2');
    if (pwd2) pwd2.value = '';

    const resultsSection = document.getElementById('resultsSection');
    if (resultsSection) resultsSection.style.display = 'none';
  }

  // Init on load
  document.addEventListener('DOMContentLoaded', function () {
    initTabs();

    // Current panels
    setupFileHandler('certFile', 'certContent');
    setupFileHandler('keyFile', 'keyContent');
    setupFileHandler('certFile2', 'certContent2');
    setupFileHandler('pubFile', 'pubContent');
    setupFileHandler('cert1File', 'cert1Content');
    setupFileHandler('cert2File', 'cert2Content');

    // New panel (public key + private key)
    setupFileHandler('pubFile2', 'pubContent2');
    setupFileHandler('keyFile2', 'keyContent2');
  });
})();

