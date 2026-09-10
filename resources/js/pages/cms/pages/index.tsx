import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Pencil, Trash2 } from 'lucide-react';

interface PageRow {
    id: number;
    slug: string;
    title: string;
}

interface Props {
    pages: PageRow[];
}

export default function SitePagesIndex({ pages }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'CMS', href: route('cms.index') },
        { title: 'Pages', href: '#' },
    ];

    const destroy = (id: number) => {
        router.delete(route('cms.pages.destroy', { page: id }));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pages" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Pages</h1>
                        <p className="text-muted-foreground text-sm">Static content pages — link to these from the header menu in Site settings.</p>
                    </div>
                    <Button asChild>
                        <Link href={route('cms.pages.create')}>New page</Link>
                    </Button>
                </div>

                {pages.length === 0 ? (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex min-h-[30vh] flex-col items-center justify-center gap-2 rounded-xl border text-center">
                        <FileText className="text-muted-foreground size-8" />
                        <p className="text-muted-foreground text-sm">No pages yet.</p>
                    </div>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2">
                        {pages.map((page) => (
                            <Card key={page.id}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                    <CardTitle className="text-base font-medium">{page.title}</CardTitle>
                                </CardHeader>
                                <CardContent className="flex items-center justify-between">
                                    <a
                                        href={route('site.pages.show', { slug: page.slug })}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-muted-foreground text-sm hover:underline"
                                    >
                                        /pages/{page.slug}
                                    </a>
                                    <div className="flex gap-2">
                                        <Button asChild variant="outline" size="icon">
                                            <Link href={route('cms.pages.edit', { page: page.id })}>
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        <Button variant="destructive" size="icon" onClick={() => destroy(page.id)}>
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
