( function ( w, d ) {

	var source  = new EventSource( '/breaking-news', { withCredentials: false } ),
		events  = {};

	source.addEventListener( 'newstory', function ( event ) {
		if  ( event.origin !== d.origin ) {
			alert( 'Bad origin in breaking news request. Possible XSS attack.' );
			return;
		}

		// Event already handled.
		if ( events[ event.lastEventId ] ) {
			return;
		}

		events[ event.lastEventId ] = JSON.parse( event.data );

		Object.values( events ).forEach( function( story ) {
			var existing = d.querySelector( '#breaking-news-' + story.id );

			// Remove or skip expired stories.
			if ( story.expires < Date.now() ) {
				if ( existing ) {
					existing.parentNode.removeChild( existing );
				}
				return;
			}

			var text = d.createTextNode( story.title ),
				item = d.createElement( 'p' );

			if ( story.link ) {
				var anchor  = d.createElement( 'a' );
				anchor.href = story.link;
				anchor.appendChild( text );
				item.appendChild( anchor );
			} else {
				item.appendChild( text );
			}

			item.id = 'breaking-news-' + story.id;
			item.className = 'breaking-news__item';

			// Update or add story.
			if ( existing ) {
				existing.parentNode.replaceChild( item, existing );
			} else {
				d.querySelector( '.breaking-news__list' ).appendChild( item );
				handleHasItems();

				// Remove on expiry.
				setTimeout( function () {
					item.parentNode.removeChild( item );
					handleHasItems();
				}, story.expires - Date.now() );
			}
		} );

	}, false );

	function handleHasItems() {
		if ( d.querySelectorAll( '.breaking-news__item' ).length > 0 ) {
			d.querySelector( '.breaking-news' ).className = 'breaking-news has-items';
		} else {
			d.querySelector( '.breaking-news' ).className = 'breaking-news';
		}
	}

} )( window, document );
