import { Input } from '@/components/ui/input';
import { Eye, EyeOff } from 'lucide-react';
import { useState, type ComponentPropsWithoutRef } from 'react';

/**
 * A password field you can read back.
 *
 * Masking exists to defeat shoulder-surfing, and on a laptop at a desk that
 * threat is usually absent while the cost — mistyping a long generated
 * password with no way to check it — is constant. The toggle is opt-in, starts
 * masked, and never persists: the next field, and the next page load, are
 * masked again.
 *
 * `autoComplete` stays on the input so password managers still recognise it,
 * and the button is `tabIndex={-1}` so tabbing runs email → password → submit
 * without a detour.
 */
export function PasswordField({ className, ...props }: ComponentPropsWithoutRef<'input'>) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input {...props} type={visible ? 'text' : 'password'} className={className} />

            <button
                type="button"
                tabIndex={-1}
                onClick={() => setVisible((value) => !value)}
                aria-label={visible ? 'Hide password' : 'Show password'}
                aria-pressed={visible}
                className="absolute inset-y-0 right-0 flex items-center px-3 transition-colors"
                style={{ color: 'var(--u-text-muted)' }}
            >
                {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
        </div>
    );
}
