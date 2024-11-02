let booksterCPSWDomReady = function(callback) {
	document.readyState === "interactive" || document.readyState === "complete" ? callback() : document.addEventListener("DOMContentLoaded", callback);
};

booksterCPSWDomReady(() => {
	let bookster_shortcode = document.getElementById('bookster-cpsw-shortcode');

	if(bookster_shortcode != null) {
		bookster_shortcode.addEventListener('click', booksterCPSWToClipboard(bookster_shortcode));
		bookster_shortcode.addEventListener('focus', booksterCPSWToClipboard(bookster_shortcode));
	}

	function booksterCPSWToClipboard(item){ 
		navigator.clipboard.writeText(item.value).then(
			() => {
				alert('Shortcode copied to clipboard');
			}
		);
	}
});

