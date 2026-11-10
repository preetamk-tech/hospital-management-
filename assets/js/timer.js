// Token Timer Functions
let timerInterval = null;

function startTokenTimer(minutes, elementId) {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    
    let seconds = minutes * 60;
    const element = document.getElementById(elementId);
    
    timerInterval = setInterval(function() {
        if (seconds <= 0) {
            clearInterval(timerInterval);
            timerInterval = null;
            if (element) element.innerHTML = 'Any moment now!';
        } else {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            if (element) element.innerHTML = mins + ':' + secs.toString().padStart(2, '0');
            seconds--;
        }
    }, 1000);
}

function stopTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

// Estimated time calculator
function calculateWaitTime(position, avgTimePerPatient) {
    avgTimePerPatient = avgTimePerPatient || 10;
    return position * avgTimePerPatient;
}

// Countdown Timer
function startCountdown(seconds, elementId, onComplete) {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    
    const element = document.getElementById(elementId);
    
    timerInterval = setInterval(function() {
        if (seconds <= 0) {
            clearInterval(timerInterval);
            timerInterval = null;
            if (onComplete) onComplete();
            if (element) element.innerHTML = "Time's up!";
        } else {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            if (element) element.innerHTML = mins + ':' + secs.toString().padStart(2, '0');
            seconds--;
        }
    }, 1000);
}

// Format time display
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins + ':' + secs.toString().padStart(2, '0');
}