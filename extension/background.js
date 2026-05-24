// background.js

let lastUrl = '';
let lastTime = 0;

chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
    if (changeInfo.status === 'complete' && tab.url && !tab.url.startsWith('chrome://')) {
        // Prevent duplicate calls for the same URL too quickly
        const now = Date.now();
        if (tab.url === lastUrl && now - lastTime < 5000) return;
        
        lastUrl = tab.url;
        lastTime = now;

        chrome.storage.local.get(['childId', 'apiUrl'], (data) => {
            if (data.childId && data.apiUrl) {
                sendDataToAPI(data.apiUrl, data.childId, tab.url, tab.title || '');
            } else {
                console.warn('Projet Protect: Missing config (childId or apiUrl)');
            }
        });
    }
});

function sendDataToAPI(apiUrl, childId, url, title) {
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            child_id: childId,
            url: url,
            title: title,
            is_blocked: 0 // Assume allowed by default; extension doesn't block directly here
        })
    })
    .then(response => response.json())
    .then(data => console.log('Projet Protect: Data sent successfully', data))
    .catch(error => console.error('Projet Protect API Error:', error));
}
