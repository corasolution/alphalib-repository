import FileDrop from '@/components/file-drop';
import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { ChangeEvent, FormEventHandler } from 'react';

interface HeroSlide {
    id: number;
    url: string;
    caption: string | null;
}

interface Props {
    heroSlides: HeroSlide[];
}

export default function CmsHeroSlides({ heroSlides }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'CMS', href: route('cms.index') },
        { title: 'Hero slides', href: '#' },
    ];

    const { data, setData, post, processing, errors, reset } = useForm<{ image: File | null; caption: string }>({
        image: null,
        caption: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('cms.hero-slides.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const onImageChange = (e: ChangeEvent<HTMLInputElement>) => {
        setData('image', e.target.files?.[0] ?? null);
    };

    const removeSlide = (id: number) => {
        router.delete(route('cms.hero-slides.destroy', { heroSlide: id }), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Hero slides — CMS" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Hero slides</h1>
                    <p className="text-muted-foreground text-sm">
                        Images that rotate in the homepage banner. No slides means a plain banner with just the title.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Slides</CardTitle>
                        <CardDescription>Wide images work best — the banner crops to fill.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {heroSlides.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No slides yet.</p>
                        ) : (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {heroSlides.map((slide) => (
                                    <div key={slide.id} className="relative overflow-hidden rounded-md border">
                                        <img src={slide.url} alt={slide.caption ?? ''} className="h-24 w-full object-cover" />
                                        {slide.caption && <p className="bg-background/80 truncate px-2 py-1 text-xs">{slide.caption}</p>}
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="icon"
                                            className="absolute top-1 right-1 size-6"
                                            onClick={() => removeSlide(slide.id)}
                                        >
                                            <Trash2 className="size-3" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-2 border-t pt-4">
                            <FileDrop
                                value={data.image}
                                onChange={(file) => setData('image', file)}
                                accept=".png,.jpg,.jpeg,.webp"
                                hint="PNG, JPG or WebP — up to 5 MB"
                            />
                            <InputError message={errors.image} />
                            <Input placeholder="Caption (optional)" value={data.caption} onChange={(e) => setData('caption', e.target.value)} />
                            <InputError message={errors.caption} />
                            <Button type="submit" size="sm" disabled={processing || !data.image}>
                                Add slide
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
