import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Facebook, Instagram, Linkedin, Twitter, Youtube } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Props {
    footerText: string;
    footerTextKm: string;
    socialLinks: Record<string, string>;
}

const SOCIAL_PLATFORMS: { key: string; label: string; icon: typeof Facebook }[] = [
    { key: 'facebook', label: 'Facebook', icon: Facebook },
    { key: 'twitter', label: 'X / Twitter', icon: Twitter },
    { key: 'linkedin', label: 'LinkedIn', icon: Linkedin },
    { key: 'youtube', label: 'YouTube', icon: Youtube },
    { key: 'instagram', label: 'Instagram', icon: Instagram },
];

export default function CmsFooter({ footerText, footerTextKm, socialLinks }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'CMS', href: route('cms.index') },
        { title: 'Footer & social', href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm<{ footer_text: string; footer_text_km: string; social_links: Record<string, string> }>(
        {
            footer_text: footerText,
            footer_text_km: footerTextKm,
            social_links: socialLinks,
        },
    );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('cms.footer.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Footer & social — CMS" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Footer &amp; social</h1>
                    <p className="text-muted-foreground text-sm">Shown at the bottom of every public page.</p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Footer text</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-2">
                                <Label htmlFor="footer_text">Text</Label>
                                <textarea
                                    id="footer_text"
                                    className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm shadow-sm"
                                    value={data.footer_text}
                                    onChange={(e) => setData('footer_text', e.target.value)}
                                />
                                <InputError message={errors.footer_text} />
                            </div>

                            <div className="mt-4 grid gap-2">
                                <Label htmlFor="footer_text_km">Khmer translation (ភាសាខ្មែរ)</Label>
                                <textarea
                                    id="footer_text_km"
                                    lang="km"
                                    className="border-input bg-background min-h-24 rounded-md border px-3 py-2 text-sm shadow-sm"
                                    value={data.footer_text_km}
                                    onChange={(e) => setData('footer_text_km', e.target.value)}
                                />
                                <p className="text-muted-foreground text-xs">Shown when a visitor switches to Khmer. Blank keeps the English text.</p>
                                <InputError message={errors.footer_text_km} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Social media</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {SOCIAL_PLATFORMS.map(({ key, label, icon: Icon }) => (
                                <div key={key} className="flex items-center gap-2">
                                    <Icon className="text-muted-foreground size-4 shrink-0" />
                                    <Input
                                        placeholder={`${label} URL`}
                                        value={data.social_links[key] ?? ''}
                                        onChange={(e) => setData('social_links', { ...data.social_links, [key]: e.target.value })}
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </PageContainer>
        </AppLayout>
    );
}
