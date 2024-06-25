
window.addEventListener('load', () => {
    const _hash = window.location.hash;
    if ( _hash.match('#oauth_access_token=') ) {
        let fullURL = (window.location.href).replace('#oauth_access_token=', '&oauth_access_token=');
        console.log(fullURL, 'fullURL')
        setTimeout( () => {
            window.location.href = fullURL;
        }, 100);
    }
})