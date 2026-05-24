document.addEventListener('DOMContentLoaded', () => {
    // Load existing config
    chrome.storage.local.get(['apiUrl', 'childId'], (data) => {
        if (data.apiUrl) document.getElementById('apiUrl').value = data.apiUrl;
        if (data.childId) document.getElementById('childId').value = data.childId;
    });

    // Save config
    document.getElementById('saveBtn').addEventListener('click', () => {
        const apiUrl = document.getElementById('apiUrl').value.trim();
        const childId = parseInt(document.getElementById('childId').value.trim(), 10);
        
        if (!apiUrl || isNaN(childId)) {
            document.getElementById('status').innerText = 'Veuillez remplir tous les champs.';
            document.getElementById('status').style.color = '#ef4444';
            return;
        }

        chrome.storage.local.set({ apiUrl: apiUrl, childId: childId }, () => {
            document.getElementById('status').innerText = 'Configuration sauvegardée !';
            document.getElementById('status').style.color = '#3CB4A8';
            setTimeout(() => {
                document.getElementById('status').innerText = '';
            }, 3000);
        });
    });
});
