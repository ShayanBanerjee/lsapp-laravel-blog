import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import DeleteUser from '@/components/delete-user';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

interface OrcidState {
    available: boolean;
    id: string | null;
    name: string | null;
    linked_human: string | null;
}

export default function Profile({ mustVerifyEmail, status, orcid }: { mustVerifyEmail: boolean; status?: string; orcid?: OrcidState }) {
    const { auth } = usePage<SharedData>().props;
    // These pages are behind the `auth` middleware, so a user is always present.
    const user = auth.user!;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Profile information" description="Update your name and email address" />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>

                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoComplete="name"
                                placeholder="Full name"
                            />

                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>

                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                autoComplete="username"
                                placeholder="Email address"
                            />

                            <InputError className="mt-2" message={errors.email} />
                        </div>

                        {mustVerifyEmail && user.email_verified_at === null && (
                            <div>
                                <p className="mt-2 text-sm text-neutral-800">
                                    Your email address is unverified.
                                    <Link
                                        href={route('verification.send')}
                                        method="post"
                                        as="button"
                                        className="rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                                    >
                                        Click here to re-send the verification email.
                                    </Link>
                                </p>

                                {status === 'verification-link-sent' && (
                                    <div className="mt-2 text-sm font-medium text-green-600">
                                        A new verification link has been sent to your email address.
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </div>

                {orcid?.available && <OrcidPanel orcid={orcid} />}

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}

/**
 * ORCID is a link, not a sign-in method — see OrcidController. Linking is a
 * full-page redirect to ORCID rather than an Inertia visit, because the
 * destination is another origin.
 */
function OrcidPanel({ orcid }: { orcid: OrcidState }) {
    return (
        <div className="space-y-6">
            <HeadingSmall title="ORCID iD" description="A verified researcher identity, shown on manuscript exports and Zenodo deposits." />

            {orcid.id ? (
                <div className="space-y-4">
                    <p className="text-sm">
                        Linked to{' '}
                        <a
                            href={`https://orcid.org/${orcid.id}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-medium underline underline-offset-4"
                        >
                            {orcid.id}
                        </a>
                        {orcid.name && ` (${orcid.name})`}
                        {orcid.linked_human && ` — since ${orcid.linked_human}`}
                    </p>

                    <Button variant="secondary" onClick={() => router.delete('/auth/orcid', { preserveScroll: true })}>
                        Unlink
                    </Button>
                </div>
            ) : (
                <Button asChild>
                    <a href="/auth/orcid/redirect">Link your ORCID iD</a>
                </Button>
            )}
        </div>
    );
}
