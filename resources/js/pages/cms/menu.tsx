import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface MenuItem {
    label: string;
    url: string;
}

interface Props {
    menuItems: MenuItem[];
}

export default function CmsMenu({ menuItems }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'CMS', href: route('cms.index') },
        { title: 'Menu', href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm<{ menu_items: MenuItem[] }>({
        menu_items: menuItems,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('cms.menu.update'));
    };

    const addItem = () => setData('menu_items', [...data.menu_items, { label: '', url: '' }]);
    const removeItem = (index: number) =>
        setData(
            'menu_items',
            data.menu_items.filter((_, i) => i !== index),
        );
    const updateItem = (index: number, field: keyof MenuItem, value: string) =>
        setData(
            'menu_items',
            data.menu_items.map((item, i) => (i === index ? { ...item, [field]: value } : item)),
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Menu — CMS" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Menu</h1>
                    <p className="text-muted-foreground text-sm">Links shown in the header across the public site.</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Header menu</CardTitle>
                        <CardDescription>Point these at your content pages (e.g. /pages/ethics) or anywhere else.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            {data.menu_items.length === 0 && <p className="text-muted-foreground text-sm">No menu items yet.</p>}

                            {data.menu_items.map((item, index) => (
                                <div key={index} className="flex items-end gap-2">
                                    <div className="grid flex-1 gap-2">
                                        <Label>Label</Label>
                                        <Input value={item.label} onChange={(e) => updateItem(index, 'label', e.target.value)} />
                                        <InputError message={(errors as Record<string, string>)[`menu_items.${index}.label`]} />
                                    </div>
                                    <div className="grid flex-1 gap-2">
                                        <Label>URL</Label>
                                        <Input value={item.url} onChange={(e) => updateItem(index, 'url', e.target.value)} />
                                        <InputError message={(errors as Record<string, string>)[`menu_items.${index}.url`]} />
                                    </div>
                                    <Button type="button" variant="outline" size="icon" onClick={() => removeItem(index)}>
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}

                            <div className="flex gap-2">
                                <Button type="button" variant="outline" size="sm" onClick={addItem}>
                                    Add menu item
                                </Button>
                                <Button type="submit" size="sm" disabled={processing}>
                                    Save
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}
