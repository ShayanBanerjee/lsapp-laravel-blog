<?php

namespace App\Support\Integrations\Providers;

use App\Support\Academic\Manuscript;
use App\Support\Integrations\PublishesPosts;
use App\Support\Integrations\PushResult;
use Illuminate\Support\Facades\Http;

/**
 * Instapaper.
 *
 * A caveat worth stating rather than burying: Instapaper's Simple API
 * authenticates with the account's own username and password. There is no
 * token, and their OAuth (xAuth) variant requires consumer credentials issued
 * by request — so for a self-hosted install this is what exists.
 *
 * Two consequences are handled deliberately. The password is stored encrypted
 * like any other credential, and the setup form says outright what it is
 * asking for, so nobody types their password expecting it to be an API key.
 * Accounts without a password (Instapaper allows sign-up by email link) simply
 * leave the field empty, which their API accepts.
 */
class Instapaper implements PublishesPosts
{
    private const ENDPOINT = 'https://www.instapaper.com/api/add';

    public function key(): string
    {
        return 'instapaper';
    }

    public function label(): string
    {
        return 'Instapaper';
    }

    public function blurb(): string
    {
        return 'Send a piece to your Instapaper reading queue.';
    }

    public function credentialsUrl(): ?string
    {
        return 'https://www.instapaper.com/main/settings';
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'username', 'label' => 'Instapaper email', 'help' => 'The address you sign in with', 'secret' => false],
            [
                'key' => 'password',
                'label' => 'Instapaper password',
                'help' => 'Instapaper has no API tokens — their Simple API takes the account password. Leave empty if your account has none.',
                'secret' => true,
            ],
        ];
    }

    public function verify(array $credentials): PushResult
    {
        // The authenticate endpoint answers 403 for bad credentials and 200
        // for good ones, without adding anything to the queue.
        $response = Http::asForm()->post('https://www.instapaper.com/api/authenticate', [
            'username' => $credentials['username'] ?? '',
            'password' => $credentials['password'] ?? '',
        ]);

        return $response->successful()
            ? PushResult::success('Connected to Instapaper.')
            : PushResult::failure('Instapaper did not accept those details.');
    }

    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult
    {
        $response = Http::asForm()->post(self::ENDPOINT, [
            'username' => $credentials['username'] ?? '',
            'password' => $credentials['password'] ?? '',
            'url' => $manuscript->url,
            'title' => $manuscript->title,
            'selection' => mb_substr($manuscript->abstract, 0, 300),
        ]);

        if ($response->failed()) {
            return PushResult::failure('Instapaper refused it.');
        }

        return PushResult::success('Added to your Instapaper queue.', 'https://www.instapaper.com/u');
    }
}
