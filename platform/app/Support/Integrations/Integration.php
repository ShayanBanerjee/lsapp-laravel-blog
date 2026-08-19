<?php

namespace App\Support\Integrations;

/**
 * One third-party service a writer can connect.
 *
 * Every provider declares the credentials it needs rather than the settings UI
 * knowing about any particular service — adding an integration is one class and
 * a registry line, with no form to edit.
 *
 * `verify()` is required, not optional. A connection that is only tested the
 * first time someone tries to publish fails at the worst possible moment, with
 * a finished piece and an error nobody can act on.
 */
interface Integration
{
    public function key(): string;

    public function label(): string;

    /** One line on what connecting this actually does. */
    public function blurb(): string;

    /**
     * Where the user gets the credential, so the setup form can link to it.
     */
    public function credentialsUrl(): ?string;

    /**
     * @return array<int, array{key: string, label: string, help: string, secret: bool}>
     */
    public function credentialFields(): array;

    /**
     * Confirm the credentials work, before anything is stored as connected.
     *
     * @param  array<string, string>  $credentials
     */
    public function verify(array $credentials): PushResult;
}
