wikipedia_test
==============

This is simple approach with php and the idea behind it is to use memcache for caching and for locking approach.

This approach include merging revisions based on appending file.


System requirements:

1. Web Server with php 5.x

2. Memcached Server on the same host (localhost:11211)


The Task:

Building an API for a mini-Wikipedia with only a single article called 'Latest_plane_crash'. Just after a plane crash happened, there is a surge of API requests for this article from app and desktop users (>20k req/s). As an approximation for some data massaging, each request for the article in your server needs to recursively calculate fibonacci(34).

At the same time, a lot of editors following the news are scrambling to update the page as details emerge (up to 10 attempted edits/s). Editing happens by downloading the current revision of the text, modifying it and posting it back to the API. The article contains HTML, and should be persisted stored as a plain file on disk. Your code will run on a single 12-core server.

Please design and implement a simple server providing this API using an environment of your choice. Please describe which other resources you'd use in production to handle the request rates mentioned, and how you'd interact with those resources.
