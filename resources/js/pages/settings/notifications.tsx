import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Send } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Preferences {
    mail: boolean;
    telegram: boolean;
    muted: string[];
    telegramLinked: boolean;
    telegramConfigured: boolean;
}

interface Props {
    preferences: Preferences;
    groups: Record<string, Record<string, string>>;
    telegramLink: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notification settings', href: '/settings/notifications' }];

export default function Notifications({ preferences, groups, telegramLink }: Props) {
    const form = useForm<{ mail: boolean; telegram: boolean; muted: string[] }>({
        mail: preferences.mail,
        telegram: preferences.telegram,
        muted: preferences.muted,
    });
    const { data, setData, processing } = form;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('notifications.update'), { preserveScroll: true });
    };

    // Stored as a mute list rather than a subscribe list, so a template added later is ON for
    // everyone by default. A reviewer should never miss a new kind of message purely because it
    // did not exist when they last visited this screen.
    const toggleMute = (key: string, muted: boolean) => setData('muted', muted ? [...data.muted, key] : data.muted.filter((k) => k !== key));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification settings" />
            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Notifications" description="How and when we contact you about submissions and reviews." />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="space-y-4">
                            <label className="flex items-start gap-3">
                                <Checkbox checked={data.mail} onCheckedChange={(c) => setData('mail', c === true)} className="mt-0.5" />
                                <span className="text-sm">
                                    <span className="font-medium">Email</span>
                                    <span className="text-muted-foreground block">Sent to your account address.</span>
                                </span>
                            </label>

                            <div className="space-y-2">
                                <label className="flex items-start gap-3">
                                    <Checkbox
                                        checked={data.telegram}
                                        disabled={!preferences.telegramLinked}
                                        onCheckedChange={(c) => setData('telegram', c === true)}
                                        className="mt-0.5"
                                    />
                                    <span className="text-sm">
                                        <span className="font-medium">Telegram</span>
                                        <span className="text-muted-foreground block">
                                            {preferences.telegramLinked
                                                ? 'Sent to the chat you connected.'
                                                : 'Connect your Telegram below to enable this.'}
                                        </span>
                                    </span>
                                </label>

                                <div className="ml-7">
                                    {preferences.telegramLinked ? (
                                        <span className="flex flex-wrap items-center gap-3 text-sm">
                                            <span className="text-primary inline-flex items-center gap-1.5">
                                                <CheckCircle2 className="size-4" />
                                                Connected
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => router.delete(route('notifications.telegram.unlink'), { preserveScroll: true })}
                                            >
                                                Disconnect
                                            </Button>
                                        </span>
                                    ) : !preferences.telegramConfigured ? (
                                        <p className="text-muted-foreground text-sm">
                                            This installation has no Telegram bot configured, so there is nothing to connect to yet.
                                        </p>
                                    ) : telegramLink ? (
                                        <Button asChild type="button" variant="outline" size="sm">
                                            {/* Leaves the app entirely, so a plain anchor rather than an Inertia Link. */}
                                            <a href={telegramLink} target="_blank" rel="noopener noreferrer">
                                                <Send className="mr-1.5 size-4" />
                                                Connect Telegram
                                            </a>
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        </div>

                        <div className="space-y-5 border-t pt-5">
                            <p className="text-muted-foreground text-sm">
                                Untick anything you would rather not be told about. This applies to every channel you have on.
                            </p>

                            {Object.entries(groups).map(([group, templates]) => (
                                <div key={group} className="space-y-2">
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{group}</p>
                                    {Object.entries(templates).map(([key, label]) => (
                                        <label key={key} className="flex items-start gap-3 text-sm">
                                            <Checkbox
                                                checked={!data.muted.includes(key)}
                                                onCheckedChange={(c) => toggleMute(key, c !== true)}
                                                className="mt-0.5"
                                            />
                                            <span>{label}</span>
                                        </label>
                                    ))}
                                </div>
                            ))}
                        </div>

                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
