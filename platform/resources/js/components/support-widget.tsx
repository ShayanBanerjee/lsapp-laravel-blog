import { Action } from '@/components/action';
import { Panel } from '@/components/metal';
import { router, useForm } from '@inertiajs/react';
import { Check, Heart } from 'lucide-react';
import { useState } from 'react';

export interface SupportConfig {
    presets: number[];
    tiers: number[];
    currency_symbol: string;
    rate_percent: string;
    /** Already a member of this voice. */
    member: boolean;
    handle: string | null;
    /** Own work — the widget hides itself rather than offering self-payment. */
    is_mine: boolean;
    signed_in: boolean;
}

const minor = (symbol: string, amount: number) => (amount % 100 === 0 ? `${symbol}${amount / 100}` : `${symbol}${(amount / 100).toFixed(2)}`);

/**
 * Paying a writer.
 *
 * A tip and a membership are offered side by side but never merged into one
 * "amount" control: a tip answers *this piece*, a membership backs *this
 * voice*, and they mean different things to both parties.
 *
 * The platform's share is printed on the widget itself rather than buried in
 * terms. A reader deciding whether £5 is worth it should be able to see what
 * reaches the writer without going and looking for it.
 */
export function SupportWidget({ config, slug }: { config: SupportConfig; slug?: string }) {
    const [amount, setAmount] = useState(config.presets[1] ?? config.presets[0]);
    const [anonymous, setAnonymous] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        amount_minor: config.presets[1] ?? config.presets[0],
        message: '',
        anonymous: false as boolean,
    });

    if (config.is_mine) return null;

    if (!config.signed_in) {
        return (
            <Panel className="p-5 text-center">
                <p className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                    <a href="/login" style={{ color: 'var(--u-accent)' }}>
                        Log in
                    </a>{' '}
                    to support this writer directly.
                </p>
            </Panel>
        );
    }

    return (
        <Panel className="p-5 sm:p-6">
            <span className="flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                <Heart className="size-3.5" style={{ color: 'var(--u-accent)' }} />
                Support this writer
            </span>

            {slug && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(`/posts/${slug}/tip`, { preserveScroll: true, onSuccess: () => reset() });
                    }}
                    className="mt-4"
                >
                    <div className="flex flex-wrap gap-2">
                        {config.presets.map((preset) => {
                            const active = amount === preset;

                            return (
                                <button
                                    key={preset}
                                    type="button"
                                    onClick={() => {
                                        setAmount(preset);
                                        setData('amount_minor', preset);
                                    }}
                                    className="rounded-[9px] border px-3.5 py-2 text-sm tabular-nums transition-colors"
                                    style={{
                                        borderColor: active ? 'var(--u-accent)' : 'var(--u-border)',
                                        backgroundColor: active ? 'var(--u-accent-soft)' : 'transparent',
                                        color: active ? 'var(--u-accent)' : 'var(--u-text-muted)',
                                    }}
                                >
                                    {minor(config.currency_symbol, preset)}
                                </button>
                            );
                        })}
                    </div>

                    <textarea
                        value={data.message}
                        onChange={(event) => setData('message', event.target.value)}
                        rows={2}
                        maxLength={500}
                        placeholder="Say what landed (optional)"
                        aria-label="Message to the writer"
                        className="u-field mt-3 text-sm"
                    />

                    <label className="mt-3 flex items-center gap-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                        <input
                            type="checkbox"
                            checked={anonymous}
                            onChange={(event) => {
                                setAnonymous(event.target.checked);
                                setData('anonymous', event.target.checked);
                            }}
                            className="accent-[var(--u-accent)]"
                        />
                        Send anonymously
                    </label>

                    <Action type="submit" loading={processing} className="mt-4 w-full" icon={<Heart className="size-4" />}>
                        Send {minor(config.currency_symbol, amount)}
                    </Action>
                </form>
            )}

            {config.handle && (
                <div className="mt-5 border-t pt-5" style={{ borderColor: 'var(--u-border)' }}>
                    <p className="text-xs" style={{ color: 'var(--u-text-muted)' }}>
                        {config.member ? 'You are a member of this voice.' : 'Or back this voice every month:'}
                    </p>

                    {config.member ? (
                        <Action
                            variant="ghost"
                            size="sm"
                            className="mt-3"
                            icon={<Check className="size-3.5" />}
                            onClick={() => router.delete(`/personas/${config.handle}/membership`, { preserveScroll: true })}
                        >
                            Cancel membership
                        </Action>
                    ) : (
                        <div className="mt-3 flex flex-wrap gap-2">
                            {config.tiers.map((tier) => (
                                <Action
                                    key={tier}
                                    variant="ghost"
                                    size="sm"
                                    magnetic={3}
                                    onClick={() =>
                                        router.post(`/personas/${config.handle}/membership`, { amount_minor: tier }, { preserveScroll: true })
                                    }
                                >
                                    {minor(config.currency_symbol, tier)}/mo
                                </Action>
                            ))}
                        </div>
                    )}
                </div>
            )}

            <p className="mt-4 text-[11px] leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                {config.rate_percent} goes to running the platform and covers the card fees. The rest reaches the writer.
            </p>
        </Panel>
    );
}
