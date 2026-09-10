import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Check, ExternalLink, RotateCcw } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Template {
    key: string;
    name: string;
    description: string;
    palette: Record<string, string>;
}

interface Props {
    templates: Template[];
    current: string;
    fonts: { key: string; name: string }[];
    currentFont: string;
    palette: Record<string, string>;
    overrides: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CMS', href: route('cms.index') },
    { title: 'Appearance', href: '#' },
];

/** The colours worth exposing. The rest of the palette follows from the template. */
const EDITABLE = [
    { token: 'primary', label: 'Primary', hint: 'Header, buttons, links' },
    { token: 'primary-dark', label: 'Primary dark', hint: 'Hero band and footer' },
    { token: 'accent', label: 'Accent', hint: 'Highlights and secondary text' },
    { token: 'surface', label: 'Surface', hint: 'Tinted panels' },
];

/** A row of the template's colours, so the picker shows the design rather than only naming it. */
function Swatches({ palette }: { palette: Record<string, string> }) {
    return (
        <div className="flex gap-1">
            {['primary-dark', 'primary', 'accent', 'surface'].map((token) => (
                <span
                    key={token}
                    className="size-5 rounded-full border border-black/10"
                    style={{ backgroundColor: palette[token] }}
                    title={`${token}: ${palette[token]}`}
                />
            ))}
        </div>
    );
}

export default function CmsAppearance({ templates, current, fonts, currentFont, palette, overrides }: Props) {
    const form = useForm<{ template: string; font: string; palette_overrides: Record<string, string> }>({
        template: current,
        font: currentFont,
        palette_overrides: { ...overrides },
    });
    const { data, setData, processing, errors } = form;

    const selected = templates.find((t) => t.key === data.template) ?? templates[0];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('cms.appearance.update'), { preserveScroll: true });
    };

    // An override is only meaningful against the template's own value, so switching template
    // re-bases what "unchanged" means. Overrides deliberately survive the switch — a university
    // that set its brand colour should not lose it by trying another layout.
    const valueFor = (token: string) => data.palette_overrides[token] ?? selected?.palette[token] ?? palette[token] ?? '#000000';

    const setColour = (token: string, value: string) => setData('palette_overrides', { ...data.palette_overrides, [token]: value });

    const resetColour = (token: string) => {
        const next = { ...data.palette_overrides };
        delete next[token];
        setData('palette_overrides', next);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appearance" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Appearance</h1>
                    <p className="text-muted-foreground text-sm">
                        How the public site looks. Every template shows the same content — only the design changes.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Template</CardTitle>
                            <CardDescription>
                                Each has its own layout and colours. Preview one on the live site before committing to it.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            {templates.map((template) => {
                                const active = data.template === template.key;

                                return (
                                    <div
                                        key={template.key}
                                        className={cn(
                                            'rounded-lg border transition-colors',
                                            active ? 'border-primary bg-primary/5' : 'hover:border-primary/40',
                                        )}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => setData('template', template.key)}
                                            aria-pressed={active}
                                            className="w-full px-4 pt-4 text-left"
                                        >
                                            <span className="flex items-center justify-between gap-3">
                                                <span className="font-medium">{template.name}</span>
                                                {active && <Check className="text-primary size-4 shrink-0" />}
                                            </span>
                                            <span className="text-muted-foreground mt-1 block text-sm">{template.description}</span>
                                        </button>
                                        <div className="flex items-center justify-between gap-3 px-4 pt-3 pb-4">
                                            <Swatches palette={template.palette} />
                                            {/* Opens the real site, not a thumbnail — choosing a design from a
                                                picture is guessing. Preview is admin-only server-side. */}
                                            <a
                                                href={`/?preview_template=${template.key}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label={`Preview the ${template.name} template`}
                                                className="text-muted-foreground hover:text-primary inline-flex items-center gap-1 text-xs"
                                            >
                                                Preview
                                                <ExternalLink className="size-3" />
                                            </a>
                                        </div>
                                    </div>
                                );
                            })}
                            <InputError message={errors.template} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Colours</CardTitle>
                            <CardDescription>
                                Optional. Leave these alone to use the template&apos;s own palette; set one to match your institution&apos;s brand.
                                Overrides are kept if you switch template.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {EDITABLE.map(({ token, label, hint }) => {
                                const overridden = data.palette_overrides[token] !== undefined;

                                return (
                                    <div key={token} className="grid gap-1.5">
                                        <Label htmlFor={`colour_${token}`}>{label}</Label>
                                        <div className="flex items-center gap-2">
                                            <input
                                                id={`colour_${token}`}
                                                type="color"
                                                value={valueFor(token)}
                                                onChange={(e) => setColour(token, e.target.value)}
                                                className="border-input h-9 w-12 shrink-0 cursor-pointer rounded border bg-transparent"
                                            />
                                            <Input
                                                value={valueFor(token)}
                                                onChange={(e) => setColour(token, e.target.value)}
                                                className="h-9 font-mono text-sm"
                                            />
                                            {overridden && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-9 shrink-0"
                                                    aria-label={`Reset ${label}`}
                                                    onClick={() => resetColour(token)}
                                                >
                                                    <RotateCcw className="size-4" />
                                                </Button>
                                            )}
                                        </div>
                                        <p className="text-muted-foreground text-xs">
                                            {overridden ? 'Overridden — reset to follow the template.' : hint}
                                        </p>
                                        <InputError message={errors[`palette_overrides.${token}` as keyof typeof errors]} />
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Typeface</CardTitle>
                            <CardDescription>Khmer faces stay in every pairing, so switching type never breaks Khmer text.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-3">
                            {fonts.map((font) => (
                                <button
                                    key={font.key}
                                    type="button"
                                    onClick={() => setData('font', font.key)}
                                    aria-pressed={data.font === font.key}
                                    className={cn(
                                        'rounded-lg border p-3 text-left text-sm transition-colors',
                                        data.font === font.key ? 'border-primary bg-primary/5' : 'hover:border-primary/40',
                                    )}
                                >
                                    {font.name}
                                </button>
                            ))}
                            <InputError message={errors.font} />
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing}>
                        Save appearance
                    </Button>
                </form>
            </PageContainer>
        </AppLayout>
    );
}
