import FileDrop from '@/components/file-drop';
import InputError from '@/components/input-error';
import PageContainer from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Pencil, Plus, Trash2, X } from 'lucide-react';
import { ChangeEvent, FormEventHandler, useState } from 'react';

interface Member {
    id: number;
    name: string;
    role: string;
    affiliation: string;
    photoUrl: string | null;
    initials: string;
    sortOrder: number;
}

interface Group {
    id: number;
    name: string;
    members: Member[];
}

interface Props {
    groups: Group[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'CMS', href: route('cms.index') },
    { title: 'Editorial board', href: '#' },
];

/** Reorder control shared by groups and people — up/down rather than drag. */
function MoveButtons({ onMove, disableUp, disableDown }: { onMove: (d: 'up' | 'down') => void; disableUp: boolean; disableDown: boolean }) {
    return (
        <div className="flex flex-col">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-6"
                aria-label="Move up"
                disabled={disableUp}
                onClick={() => onMove('up')}
            >
                <ChevronUp className="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-6"
                aria-label="Move down"
                disabled={disableDown}
                onClick={() => onMove('down')}
            >
                <ChevronDown className="size-4" />
            </Button>
        </div>
    );
}

/**
 * Add or edit one person. The same form serves both, because the fields are identical and the
 * only difference is which route it posts to — duplicating it would mean every future field has
 * to be added twice, which is exactly how the two drift apart.
 */
function MemberForm({ groupId, member, onDone }: { groupId: number; member?: Member; onDone: () => void }) {
    const form = useForm<{ name: string; role: string; affiliation: string; photo: File | null; remove_photo: boolean }>({
        name: member?.name ?? '',
        role: member?.role ?? '',
        affiliation: member?.affiliation ?? '',
        photo: null,
        remove_photo: false,
    });
    const { data, setData, post, processing, errors } = form;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // POST for both: the update route takes a file, and multipart cannot ride a real PUT.
        post(member ? route('cms.board.members.update', { boardMember: member.id }) : route('cms.board.members.store', { boardGroup: groupId }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    const onPhotoChange = (e: ChangeEvent<HTMLInputElement>) => setData('photo', e.target.files?.[0] ?? null);

    return (
        <form onSubmit={submit} className="bg-muted/40 grid gap-3 rounded-md border p-4">
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor={`name_${member?.id ?? 'new'}_${groupId}`}>Name</Label>
                    <Input
                        id={`name_${member?.id ?? 'new'}_${groupId}`}
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Dr. Reth Chanthoun"
                    />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`role_${member?.id ?? 'new'}_${groupId}`}>Role (optional)</Label>
                    <Input
                        id={`role_${member?.id ?? 'new'}_${groupId}`}
                        value={data.role}
                        onChange={(e) => setData('role', e.target.value)}
                        placeholder="Professor of Economics"
                    />
                    <InputError message={errors.role} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`aff_${member?.id ?? 'new'}_${groupId}`}>Affiliation (optional)</Label>
                    <Input
                        id={`aff_${member?.id ?? 'new'}_${groupId}`}
                        value={data.affiliation}
                        onChange={(e) => setData('affiliation', e.target.value)}
                        placeholder="BELTEI International University, Cambodia"
                    />
                    <InputError message={errors.affiliation} />
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-4">
                <div className="grid gap-1.5">
                    <Label htmlFor={`photo_${member?.id ?? 'new'}_${groupId}`} className="text-muted-foreground text-xs">
                        Photo (optional — an initials circle is shown without one)
                    </Label>
                    <FileDrop
                        value={data.photo}
                        onChange={(file) => setData('photo', file)}
                        accept=".png,.jpg,.jpeg,.webp"
                        label="Drop a portrait here, or click to browse"
                        hint="PNG, JPG or WebP — up to 5 MB"
                    />
                    <InputError message={errors.photo} />
                </div>
                {member?.photoUrl && (
                    <label className="text-muted-foreground flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.remove_photo} onChange={(e) => setData('remove_photo', e.target.checked)} />
                        Remove current photo
                    </label>
                )}
            </div>

            <div className="flex items-center gap-2">
                <Button type="submit" size="sm" disabled={processing || !data.name.trim()}>
                    {member ? 'Save' : 'Add person'}
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

export default function CmsEditorialBoard({ groups }: Props) {
    const [addingTo, setAddingTo] = useState<number | null>(null);
    const [editing, setEditing] = useState<number | null>(null);
    const [renaming, setRenaming] = useState<number | null>(null);

    const newGroup = useForm({ name: '' });

    const addGroup: FormEventHandler = (e) => {
        e.preventDefault();
        newGroup.post(route('cms.board.groups.store'), { preserveScroll: true, onSuccess: () => newGroup.reset() });
    };

    const move = (kind: 'groups' | 'members', id: number, direction: 'up' | 'down') => {
        router.post(
            kind === 'groups' ? route('cms.board.groups.move', { boardGroup: id }) : route('cms.board.members.move', { boardMember: id }),
            { direction },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editorial board" />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Editorial board</h1>
                    <p className="text-muted-foreground text-sm">
                        Groups and people shown on any page whose layout is set to “Editorial board”. Order here is the order readers see.
                    </p>
                </div>

                {groups.length === 0 && (
                    <p className="text-muted-foreground rounded-md border border-dashed p-8 text-center text-sm">
                        No groups yet. Add one below to get started.
                    </p>
                )}

                {groups.map((group, groupIndex) => (
                    <Card key={group.id}>
                        <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                            <div className="min-w-0 flex-1">
                                {renaming === group.id ? (
                                    <RenameGroupForm group={group} onDone={() => setRenaming(null)} />
                                ) : (
                                    <>
                                        <CardTitle className="text-base">{group.name}</CardTitle>
                                        <CardDescription>
                                            {group.members.length} {group.members.length === 1 ? 'person' : 'people'}
                                        </CardDescription>
                                    </>
                                )}
                            </div>
                            <div className="flex items-center gap-1">
                                <MoveButtons
                                    onMove={(d) => move('groups', group.id, d)}
                                    disableUp={groupIndex === 0}
                                    disableDown={groupIndex === groups.length - 1}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Rename ${group.name}`}
                                    onClick={() => setRenaming(group.id)}
                                >
                                    <Pencil className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Delete ${group.name}`}
                                    onClick={() => {
                                        // Deleting takes the people with it, so this one asks first.
                                        if (confirm(`Delete “${group.name}” and its ${group.members.length} people?`)) {
                                            router.delete(route('cms.board.groups.destroy', { boardGroup: group.id }), { preserveScroll: true });
                                        }
                                    }}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-3">
                            {group.members.map((member, memberIndex) => (
                                <div key={member.id}>
                                    {editing === member.id ? (
                                        <MemberForm groupId={group.id} member={member} onDone={() => setEditing(null)} />
                                    ) : (
                                        <div className="flex items-center gap-3 rounded-md border p-3">
                                            {member.photoUrl ? (
                                                <img src={member.photoUrl} alt="" className="size-10 shrink-0 rounded-full object-cover" />
                                            ) : (
                                                <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                                                    {member.initials}
                                                </span>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">{member.name}</p>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {[member.role, member.affiliation].filter(Boolean).join(' · ') || '—'}
                                                </p>
                                            </div>
                                            <MoveButtons
                                                onMove={(d) => move('members', member.id, d)}
                                                disableUp={memberIndex === 0}
                                                disableDown={memberIndex === group.members.length - 1}
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${member.name}`}
                                                onClick={() => setEditing(member.id)}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Remove ${member.name}`}
                                                onClick={() =>
                                                    router.delete(route('cms.board.members.destroy', { boardMember: member.id }), {
                                                        preserveScroll: true,
                                                    })
                                                }
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ))}

                            {addingTo === group.id ? (
                                <MemberForm groupId={group.id} onDone={() => setAddingTo(null)} />
                            ) : (
                                <Button type="button" variant="outline" size="sm" onClick={() => setAddingTo(group.id)}>
                                    <Plus className="mr-1 size-4" />
                                    Add person
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ))}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Add a group</CardTitle>
                        <CardDescription>A heading on the public page, such as “Editors-in-Chief”.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={addGroup} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="new_group_name">Group name</Label>
                                <Input id="new_group_name" value={newGroup.data.name} onChange={(e) => newGroup.setData('name', e.target.value)} />
                                <InputError message={newGroup.errors.name} />
                            </div>
                            <Button type="submit" disabled={newGroup.processing || !newGroup.data.name.trim()}>
                                Add group
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </PageContainer>
        </AppLayout>
    );
}

function RenameGroupForm({ group, onDone }: { group: Group; onDone: () => void }) {
    const form = useForm({ name: group.name });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('cms.board.groups.update', { boardGroup: group.id }), { preserveScroll: true, onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="flex items-center gap-2">
            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="h-8" autoFocus />
            <Button type="submit" size="sm" disabled={form.processing || !form.data.name.trim()}>
                Save
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                Cancel
            </Button>
        </form>
    );
}
