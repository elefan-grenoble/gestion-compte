function defer(method) {
    if (window.jQuery) {
        method();
    } else {
        setTimeout(function() { defer(method) }, 50);
    }
}
function onceSimpleMDEReady(method) {
    if (window.SimpleMDE) {
        defer(method);
    } else {
        setTimeout(function() { onceSimpleMDEReady(method) }, 50);
    }
}