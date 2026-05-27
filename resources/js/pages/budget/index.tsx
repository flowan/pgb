import { Head, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ProgressBar } from '@/components/budget/progress-bar';
import type { BudgetCategory, Caregiver, Client } from '@/types';

export default function BudgetIndex({
    categories,
    caregivers,
    client,
}: {
    categories: BudgetCategory[];
    caregivers: Caregiver[];
    client: Client;
}) {
    const [showCategoryForm, setShowCategoryForm] = useState(false);
    const [showExpenseForm, setShowExpenseForm] = useState(false);

    const categoryForm = useForm({
        name: '',
        allocated_amount: '',
    });

    const expenseForm = useForm({
        budget_category_id: '',
        caregiver_id: '',
        description: '',
        amount: '',
        date: new Date().toISOString().split('T')[0],
    });

    const totalAllocated = categories.reduce(
        (sum, cat) => sum + Number(cat.allocated_amount),
        0,
    );
    const totalSpent = categories.reduce(
        (sum, cat) => sum + Number(cat.spent_amount),
        0,
    );

    function submitCategory(e: React.FormEvent) {
        e.preventDefault();
        categoryForm.post(`/clients/${client.id}/budget-categories`, {
            onSuccess: () => {
                categoryForm.reset();
                setShowCategoryForm(false);
            },
        });
    }

    function submitExpense(e: React.FormEvent) {
        e.preventDefault();
        expenseForm.post(`/clients/${client.id}/budget-expenses`, {
            onSuccess: () => {
                expenseForm.reset();
                setShowExpenseForm(false);
            },
        });
    }

    function deleteExpense(expenseId: number) {
        router.delete(`/clients/${client.id}/budget-expenses/${expenseId}`);
    }

    return (
        <>
            <Head title={`Budget - ${client.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={`/clients/${client.id}`}>
                            <ArrowLeft />
                            Terug naar cliënt
                        </Link>
                    </Button>
                </div>

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">
                        Budget van {client.name}
                    </h1>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setShowCategoryForm(!showCategoryForm)}
                        >
                            <Plus />
                            Categorie toevoegen
                        </Button>
                        <Button
                            onClick={() => setShowExpenseForm(!showExpenseForm)}
                        >
                            <Plus />
                            Uitgave toevoegen
                        </Button>
                    </div>
                </div>

                {categories.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Totaal budget</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ProgressBar allocated={totalAllocated} spent={totalSpent} />
                        </CardContent>
                    </Card>
                )}

                {showCategoryForm && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Nieuwe categorie</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitCategory} className="flex flex-col gap-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="cat-name">Naam</Label>
                                        <Input
                                            id="cat-name"
                                            value={categoryForm.data.name}
                                            onChange={(e) =>
                                                categoryForm.setData('name', e.target.value)
                                            }
                                        />
                                        {categoryForm.errors.name && (
                                            <p className="text-sm text-red-500">
                                                {categoryForm.errors.name}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="cat-amount">Budget bedrag</Label>
                                        <Input
                                            id="cat-amount"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={categoryForm.data.allocated_amount}
                                            onChange={(e) =>
                                                categoryForm.setData(
                                                    'allocated_amount',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {categoryForm.errors.allocated_amount && (
                                            <p className="text-sm text-red-500">
                                                {categoryForm.errors.allocated_amount}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={categoryForm.processing}>
                                        Opslaan
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setShowCategoryForm(false)}
                                    >
                                        Annuleren
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {showExpenseForm && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Nieuwe uitgave</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitExpense} className="flex flex-col gap-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="exp-category">Categorie</Label>
                                        <Select
                                            value={expenseForm.data.budget_category_id}
                                            onValueChange={(val) =>
                                                expenseForm.setData('budget_category_id', val)
                                            }
                                        >
                                            <SelectTrigger id="exp-category">
                                                <SelectValue placeholder="Selecteer categorie" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {categories.map((cat) => (
                                                    <SelectItem
                                                        key={cat.id}
                                                        value={String(cat.id)}
                                                    >
                                                        {cat.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {expenseForm.errors.budget_category_id && (
                                            <p className="text-sm text-red-500">
                                                {expenseForm.errors.budget_category_id}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="exp-caregiver">Zorgverlener</Label>
                                        <Select
                                            value={expenseForm.data.caregiver_id}
                                            onValueChange={(val) =>
                                                expenseForm.setData('caregiver_id', val)
                                            }
                                        >
                                            <SelectTrigger id="exp-caregiver">
                                                <SelectValue placeholder="Optioneel" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {caregivers.map((cg) => (
                                                    <SelectItem
                                                        key={cg.id}
                                                        value={String(cg.id)}
                                                    >
                                                        {cg.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="exp-desc">Omschrijving</Label>
                                        <Input
                                            id="exp-desc"
                                            value={expenseForm.data.description}
                                            onChange={(e) =>
                                                expenseForm.setData('description', e.target.value)
                                            }
                                        />
                                        {expenseForm.errors.description && (
                                            <p className="text-sm text-red-500">
                                                {expenseForm.errors.description}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="exp-amount">Bedrag</Label>
                                        <Input
                                            id="exp-amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            value={expenseForm.data.amount}
                                            onChange={(e) =>
                                                expenseForm.setData('amount', e.target.value)
                                            }
                                        />
                                        {expenseForm.errors.amount && (
                                            <p className="text-sm text-red-500">
                                                {expenseForm.errors.amount}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="exp-date">Datum</Label>
                                        <Input
                                            id="exp-date"
                                            type="date"
                                            value={expenseForm.data.date}
                                            onChange={(e) =>
                                                expenseForm.setData('date', e.target.value)
                                            }
                                        />
                                        {expenseForm.errors.date && (
                                            <p className="text-sm text-red-500">
                                                {expenseForm.errors.date}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={expenseForm.processing}>
                                        Opslaan
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setShowExpenseForm(false)}
                                    >
                                        Annuleren
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {categories.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Nog geen budgetcategorieën. Voeg een categorie toe om te beginnen.
                    </div>
                ) : (
                    categories.map((category) => (
                        <Card key={category.id}>
                            <CardHeader>
                                <CardTitle className="text-base">{category.name}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <ProgressBar
                                    allocated={Number(category.allocated_amount)}
                                    spent={Number(category.spent_amount)}
                                />

                                {category.expenses && category.expenses.length > 0 && (
                                    <div className="overflow-hidden rounded-lg border">
                                        <table className="w-full text-sm">
                                            <thead className="border-b bg-muted/50">
                                                <tr>
                                                    <th className="px-4 py-2 text-left font-medium">
                                                        Datum
                                                    </th>
                                                    <th className="px-4 py-2 text-left font-medium">
                                                        Omschrijving
                                                    </th>
                                                    <th className="px-4 py-2 text-left font-medium">
                                                        Zorgverlener
                                                    </th>
                                                    <th className="px-4 py-2 text-right font-medium">
                                                        Bedrag
                                                    </th>
                                                    <th className="px-4 py-2 text-right font-medium">
                                                        Acties
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {category.expenses.map((expense) => (
                                                    <tr
                                                        key={expense.id}
                                                        className="hover:bg-muted/30"
                                                    >
                                                        <td className="px-4 py-2">
                                                            {new Date(
                                                                expense.date,
                                                            ).toLocaleDateString('nl-NL')}
                                                        </td>
                                                        <td className="px-4 py-2">
                                                            {expense.description}
                                                        </td>
                                                        <td className="px-4 py-2 text-muted-foreground">
                                                            {expense.caregiver?.name ?? '-'}
                                                        </td>
                                                        <td className="px-4 py-2 text-right">
                                                            {new Intl.NumberFormat('nl-NL', {
                                                                style: 'currency',
                                                                currency: 'EUR',
                                                            }).format(Number(expense.amount))}
                                                        </td>
                                                        <td className="px-4 py-2 text-right">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    deleteExpense(expense.id)
                                                                }
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </>
    );
}

BudgetIndex.layout = (props: { client: Client }) => ({
    breadcrumbs: [
        { title: 'Cliënten', href: '/clients' },
        { title: props.client.name, href: `/clients/${props.client.id}` },
        { title: 'Budget', href: `/clients/${props.client.id}/budget` },
    ],
});
