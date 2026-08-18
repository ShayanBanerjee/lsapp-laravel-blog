import { Panel } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import type { SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Check, Loader2, RotateCcw } from 'lucide-react';

interface FontOption {
    value: string;
    label: string;
    note: string;
}

interface Props {
    fonts: FontOption[];
    defaults: { font: string; size: number; leading: number; measure: number };
}

/**
 * Reader-controlled typography.
 *
 * The sample below is styled from the *pending* form state rather than the
 * saved settings, so the reader judges a change by reading it, not by reading
 * a number.
 */
export default function ReadingSettings({ fonts, defaults }: Props) {
    const { reading } = usePage<SharedData>().props;

    const { data, setData, put, processing, isDirty } = useForm({
        font: reading.font,
        size: reading.size,
        leading: reading.leading,
        measure: reading.measure,
    });

    // Preview uses the same stack list the server allowlists.
    const previewStack =
        data.font === reading.font ? reading.stack : `'${fonts.find((f) => f.value === data.font)?.label ?? 'Literata'}', Georgia, serif`;

    return (
        <SiteLayout>
            <Head title="Reading settings" />

            <header className="mb-10">
                <h1 className="font-display text-4xl sm:text-5xl">How you read</h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Comfort beats consistency. Set the type the way it suits your eyes and your screen — it follows you to every device you sign in
                    on.
                </p>
            </header>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    put('/settings/reading', { preserveScroll: true });
                }}
                className="grid gap-6 lg:grid-cols-[320px_1fr]"
            >
                <div className="flex flex-col gap-5">
                    <Panel className="p-5">
                        <Label>Typeface</Label>
                        <div className="mt-3 flex flex-col gap-2">
                            {fonts.map((font) => {
                                const active = data.font === font.value;

                                return (
                                    <button
                                        key={font.value}
                                        type="button"
                                        onClick={() => setData('font', font.value)}
                                        aria-pressed={active}
                                        className="rounded-[9px] border px-3.5 py-3 text-left transition-colors"
                                        style={{
                                            borderColor: active ? 'var(--u-accent)' : 'var(--u-border)',
                                            backgroundColor: active ? 'var(--u-accent-soft)' : 'transparent',
                                        }}
                                    >
                                        <span className="flex items-center gap-2">
                                            <span className="text-base" style={{ fontFamily: `'${font.label}', Georgia, serif` }}>
                                                {font.label}
                                            </span>
                                            {active && <Check className="ml-auto size-4" style={{ color: 'var(--u-accent)' }} />}
                                        </span>
                                        <span className="mt-0.5 block text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                            {font.note}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </Panel>

                    <Panel className="p-5">
                        <Slider label="Text size" value={data.size} min={15} max={26} step={1} suffix="px" onChange={(v) => setData('size', v)} />
                        <Slider label="Line spacing" value={data.leading} min={1.35} max={2.2} step={0.05} onChange={(v) => setData('leading', v)} />
                        <Slider
                            label="Line width"
                            value={data.measure}
                            min={52}
                            max={88}
                            step={2}
                            suffix=" characters"
                            onChange={(v) => setData('measure', v)}
                        />
                    </Panel>

                    <div className="flex gap-2">
                        <button type="submit" disabled={processing || !isDirty} className="u-btn u-btn-primary flex-1 py-3">
                            {processing && <Loader2 className="size-4 animate-spin" />}
                            {isDirty ? 'Save settings' : 'Saved'}
                        </button>
                        <button
                            type="button"
                            className="u-btn u-btn-ghost"
                            aria-label="Reset to defaults"
                            onClick={() => {
                                setData('font', defaults.font);
                                setData('size', defaults.size);
                                setData('leading', defaults.leading);
                                setData('measure', defaults.measure);
                            }}
                        >
                            <RotateCcw className="size-4" />
                        </button>
                    </div>
                </div>

                {/* Live sample — judged by reading it, not by the numbers. */}
                <Panel className="p-7 sm:p-10">
                    <p className="mb-6 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        Preview
                    </p>
                    <div
                        style={{
                            fontFamily: previewStack,
                            fontSize: `${data.size}px`,
                            lineHeight: data.leading,
                            maxWidth: `${data.measure}ch`,
                            fontVariantNumeric: 'oldstyle-nums proportional-nums',
                        }}
                    >
                        <h2 className="font-display mb-5 text-3xl">The Year the Moss Took the North Wall</h2>
                        <p className="mb-5">
                            It began as a discolouration you could mistake for damp, and by autumn it had the confidence of something that intended to
                            stay.
                        </p>
                        <p className="mb-5">
                            Moss does not race. It occupies. There is a difference, and the difference is patience, which is the only strategy that
                            works on stone. Every spring I told myself I would deal with it, and every spring there was something more urgent, and the
                            wall kept its own counsel through 12 seasons of my good intentions.
                        </p>
                        <p>I have stopped scraping it off. The wall is not losing.</p>
                    </div>
                </Panel>
            </form>
        </SiteLayout>
    );
}

function Label({ children }: { children: string }) {
    return (
        <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
            {children}
        </span>
    );
}

function Slider({
    label,
    value,
    min,
    max,
    step,
    suffix = '',
    onChange,
}: {
    label: string;
    value: number;
    min: number;
    max: number;
    step: number;
    suffix?: string;
    onChange: (value: number) => void;
}) {
    return (
        <div className="mb-5 last:mb-0">
            <label className="flex items-baseline justify-between">
                <Label>{label}</Label>
                <span className="text-sm tabular-nums" style={{ color: 'var(--u-accent)' }}>
                    {value}
                    {suffix}
                </span>
            </label>
            <input
                type="range"
                min={min}
                max={max}
                step={step}
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                className="u-range mt-2.5 w-full"
                aria-label={label}
            />
        </div>
    );
}
