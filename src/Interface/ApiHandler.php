<?php

namespace Fishyboat21\SimpleApi\Interface;

/**
 * Marker interface for API endpoint handler classes.
 *
 * Classes implementing this interface are discovered by RouteScanner
 * when generating the route cache. The #[Route] attribute on the class
 * or its methods declares the HTTP method(s) this handler responds to.
 */
interface ApiHandler
{
}
