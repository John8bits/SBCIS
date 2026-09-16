// Retired browser-generation entry point. Official generation belongs to the server/system.
// Kept fail-closed for older cached pages; current map pages never start this worker.
self.onmessage = event => {
    const request = event.data || {};
    self.postMessage({ requestId: request.requestId, version: request.version,
        status: 'pending_configuration', generation_enabled: false });
};
