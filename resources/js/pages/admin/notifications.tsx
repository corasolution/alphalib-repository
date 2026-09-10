import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Mail, Send, XCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Brevo {
    user: string | null;
    configured: boolean;
    source: 'database' | 'env' | 'none';
    hint: string | null;
}

interface Telegram {
    configured: boolean;
    source: 'database' | 'env' | 'none';
    hint: string | null;
    username: string | null;
    webhookConfigured: boolean;
    webhookUrl: string;
}

interface Props {
    brevo: Brevo;
    telegram: Telegram;
    mailFrom: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '#' }];

function StatusBadge({ ok, okLabel, offLabel }: { ok: boolean; okLabel: string; offLabel: string }) {
    return (
        <Badge variant={ok ? 'secondary' : 'outline'} className="gap-1">
            {ok ? <CheckCircle2 className="size-3" /> : <XCircle className="size-3" />}
            {ok ? okLabel : offLabel}
        </Badge>
    );
}

/** One stored secret. The value is never sent to the browser — only its last four characters. */
function SecretField({
    name,
    label,
    placeholder,
    hint,
    source,
}: {
    name: string;
    label: string;
    placeholder: string;
    hint: string | null;
    source: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name, value: '' });

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.notifications.secret'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('value');
                setOpen(false);
            },
        });
    };

    if (!open) {
        return (
            <div className="flex flex-wrap items-center gap-3 text-sm">
                <span className="text-muted-foreground">
                    {label}: {hint ? <code className="font-mono">{hint}</code> : 'not set'}
                    {source === 'env' && ' (from the server environment)'}
                </span>
                <Button type="button" size="sm" variant="outline" onClick={() => setOpen(true)}>
                    {hint ? 'Replace' : 'Set'}
                </Button>
            </div>
        );
    }

    return (
        <form onSubmit={save} className="grid gap-2">
            <Label htmlFor={`secret_${name}`}>{label}</Label>
            <Input
                id={`secret_${name}`}
                type="password"
                autoComplete="off"
                placeholder={placeholder}
                value={form.data.value}
                onChange={(e) => form.setData('value', e.target.value)}
            />
            <p className="text-muted-foreground text-xs">Stored encrypted. Save it blank to remove it and fall back to the server environment.</p>
            <InputError message={form.errors.value} />
            <div className="flex items-center gap-2">
                <Button type="submit" size="sm" disabled={form.processing}>
                    Save
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => {
                        form.reset('value');
                        setOpen(false);
                    }}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}

/** A plain-text field — the SMTP login and bot username are not secrets and are useful to see. */
function PlainField({ name, label, placeholder, value }: { name: string; label: string; placeholder: string; value: string | null }) {
    const form = useForm({ name, value: value ?? '' });

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('admin.notifications.secret'), { preserveScroll: true });
    };

    return (
        <form onSubmit={save} className="grid gap-2 sm:max-w-sm">
            <Label htmlFor={`plain_${name}`}>{label}</Label>
            <div className="flex items-center gap-2">
                <Input
                    id={`plain_${name}`}
                    placeholder={placeholder}
                    value={form.data.value}
                    onChange={(e) => form.setData('value', e.target.value)}
                />
                <Button type="submit" size="sm" variant="outline" disabled={form.processing}>
                    Save
                </Button>
            </div>
            <InputError message={form.errors.value} />
        </form>
    );
}

export default function AdminNotifications({ brevo, telegram, mailFrom }: Props) {
    const errors = usePage().props.errors as Record<string, string> | undefined;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Notifications</h1>
                    <p className="text-muted-foreground text-sm">
                        How review and editorial messages actually reach people. Templates themselves live per journal.
                    </p>
                </div>

                {errors?.value && <InputError message={errors.value} />}

                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                        <div>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Mail className="size-4" />
                                Email (Brevo SMTP)
                            </CardTitle>
                            <CardDescription>
                                Sending as <code className="font-mono">{mailFrom}</code>. Brevo&apos;s free tier allows 300 emails a day.
                            </CardDescription>
                        </div>
                        <StatusBadge ok={brevo.configured} okLabel="Configured" offLabel="Not set" />
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <PlainField name="brevo_smtp_user" label="SMTP login" placeholder="you@example.com" value={brevo.user} />
                        <SecretField name="brevo_smtp_key" label="SMTP key" placeholder="xsmtpsib-…" hint={brevo.hint} source={brevo.source} />
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={!brevo.configured}
                            onClick={() => router.post(route('admin.notifications.test-email'), {}, { preserveScroll: true })}
                        >
                            Send myself a test email
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                        <div>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Send className="size-4" />
                                Telegram
                            </CardTitle>
                            <CardDescription>
                                Create a bot with @BotFather, then paste its token and username here. Each person connects their own chat from
                                Settings &rarr; Notifications.
                            </CardDescription>
                        </div>
                        <StatusBadge ok={telegram.configured} okLabel="Configured" offLabel="Not set" />
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <PlainField name="telegram_bot_username" label="Bot username" placeholder="AlphaJournalBot" value={telegram.username} />
                        <SecretField
                            name="telegram_bot_token"
                            label="Bot token"
                            placeholder="123456:ABC-DEF…"
                            hint={telegram.hint}
                            source={telegram.source}
                        />

                        <div className="space-y-2 border-t pt-4">
                            <p className="text-sm font-medium">Webhook</p>
                            <p className="text-muted-foreground text-sm">
                                Telegram delivers updates to <code className="font-mono text-xs">{telegram.webhookUrl}</code>. It only accepts a
                                public HTTPS address, so this will not work against a local development server.
                            </p>
                            <div className="flex flex-wrap items-center gap-2">
                                <StatusBadge ok={telegram.webhookConfigured} okLabel="Registered" offLabel="Not registered" />
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={!telegram.configured}
                                    onClick={() => router.post(route('admin.notifications.telegram.register'), {}, { preserveScroll: true })}
                                >
                                    Register webhook
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    disabled={!telegram.configured}
                                    onClick={() => router.post(route('admin.notifications.telegram.test'), {}, { preserveScroll: true })}
                                >
                                    Send myself a test message
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
