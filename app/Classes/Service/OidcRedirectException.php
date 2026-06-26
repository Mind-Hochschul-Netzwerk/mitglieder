<?php
declare(strict_types=1);
namespace App\Service;

/**
 * Thrown by the OpenIdConnect client subclass instead of doing header()+exit, so the
 * authorization redirect can be returned as a regular framework Response.
 */
class OidcRedirectException extends \Exception
{
    public function __construct(private string $url)
    {
        parent::__construct('OIDC authorization redirect');
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
