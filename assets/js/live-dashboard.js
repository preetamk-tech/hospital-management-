// Live Dashboard Auto-Refresh
let refreshInterval = null;

function startLiveRefresh(departmentId) {
    departmentId = departmentId || 0;
    
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
    
    refreshInterval = setInterval(function() {
        $.ajax({
            url: SITE_URL + 'api/get_tokens.php',
            method: 'GET',
            data: { dept_id: departmentId },
            success: function(response) {
                if (response.success) {
                    updateLiveQueue(response.tokens);
                    updateLastRefreshTime();
                }
            },
            error: function() {
                console.log('Failed to refresh queue');
            }
        });
    }, 5000);
}

function updateLiveQueue(tokens) {
    const container = $('#liveQueue');
    if (!container.length) return;
    
    if (!tokens || tokens.length === 0) {
        container.html(`
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No patients in queue</p>
            </div>
        `);
        return;
    }
    
    let html = '<div class="queue-list">';
    for (var i = 0; i < tokens.length; i++) {
        var token = tokens[i];
        var priorityClass = '';
        var priorityIcon = '';
        if (token.token_prefix === 'S') {
            priorityClass = 'senior';
            priorityIcon = '<i class="fas fa-heart"></i>';
        } else if (token.token_prefix === 'E') {
            priorityClass = 'emergency';
            priorityIcon = '<i class="fas fa-ambulance"></i>';
        }
        
        html += `
            <div class="queue-item ${priorityClass}">
                <div class="token-number">#${escapeHtml(token.token_number)}</div>
                <div class="patient-info">
                    <div class="patient-name">${escapeHtml(token.patient_name)}</div>
                    <div class="doctor-name">Dr. ${escapeHtml(token.doctor_name)}</div>
                </div>
                <div class="priority-badge">${priorityIcon} ${token.priority}</div>
                <div class="status-badge status-${token.status}">${token.status}</div>
            </div>
        `;
    }
    html += '</div>';
    container.html(html);
}

function updateLastRefreshTime() {
    const element = $('#lastRefresh');
    if (element.length) {
        element.text(new Date().toLocaleTimeString());
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

$(document).ready(function() {
    // Start live refresh if on live dashboard
    if ($('#liveQueue').length) {
        startLiveRefresh();
    }
    
    // Department filter
    $('#deptFilter').on('change', function() {
        startLiveRefresh($(this).val());
    });
    
    // Manual refresh button
    $('#refreshBtn').click(function() {
        startLiveRefresh($('#deptFilter').val());
        $(this).addClass('fa-spin');
        setTimeout(function() {
            $('#refreshBtn').removeClass('fa-spin');
        }, 1000);
    });
});