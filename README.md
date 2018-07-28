# Breaking News

Add a simple breaking news ticker to your website backed by a custom post type.

Simply add your breaking news headline in the admin and publish!

Options:

- Set an expiry time in minutes
- Add a link, you can get the headline out fast and write the article later, then add the link and it'll update the display

## Caveats

### Performance

This uses [Server Sent Events](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events/Using_server-sent_events) and as such I can't vouch for the performance on high traffic websites yet.

Rather than long running responses this implementation just uses `EventSource` to poll for new events every 10 seconds or so. The response is cachable by batcache and the endpoint URL is cleared every time a breaking news story is added or updated.
