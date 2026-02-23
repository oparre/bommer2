<!DOCTYPE html>
<html>
<head>
    <title>Test ERP API Search</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>ERP Component Search API Test</h1>
    
    <div>
        <button onclick="testSearch('RES')">Test Search: RES</button>
        <button onclick="testSearch('CAP')">Test Search: CAP</button>
        <button onclick="testSearch('LED')">Test Search: LED</button>
        <button onclick="testSearch('')">Test No Search (all ERP)</button>
    </div>
    
    <div id="results"></div>

    <script>
        async function testSearch(query) {
            const resultsDiv = document.getElementById('results');
            const searchParam = query ? `&search=${encodeURIComponent(query)}` : '';
            const url = `/api/components.php?source=erp${searchParam}&limit=100`;
            
            resultsDiv.innerHTML = `<div class="test">Testing: <strong>${url}</strong><br>Loading...</div>`;
            
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                let html = '<div class="test ' + (data.success ? 'success' : 'error') + '">';
                html += '<h3>API Response</h3>';
                html += '<p><strong>URL:</strong> ' + url + '</p>';
                html += '<p><strong>Status:</strong> ' + (data.success ? 'SUCCESS' : 'ERROR') + '</p>';
                html += '<p><strong>Components Found:</strong> ' + (data.data ? data.data.length : 0) + '</p>';
                
                if (data.data && data.data.length > 0) {
                    html += '<h4>First 5 Results:</h4><pre>';
                    data.data.slice(0, 5).forEach(comp => {
                        html += `ID: ${comp.id} | PN: ${comp.part_number} | Name: ${comp.name} | Source: ${comp.source || 'N/A'} | Status: ${comp.status}\n`;
                    });
                    html += '</pre>';
                    
                    html += '<h4>Full Response (JSON):</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
                } else if (data.error) {
                    html += '<p><strong>Error:</strong> ' + data.error + '</p>';
                } else {
                    html += '<p>No components found</p>';
                }
                
                html += '</div>';
                resultsDiv.innerHTML = html;
            } catch (error) {
                resultsDiv.innerHTML = '<div class="test error">' +
                    '<h3>Request Failed</h3>' +
                    '<p><strong>Error:</strong> ' + error.message + '</p>' +
                    '</div>';
            }
        }
        
        // Auto-test on load
        window.addEventListener('load', () => {
            testSearch('RES');
        });
    </script>
</body>
</html>
