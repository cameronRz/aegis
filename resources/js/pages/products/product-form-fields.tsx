import { useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BillingInterval, PriceType, ProductType } from '@/types';

export type ProductFormData = {
    name: string;
    sku: string;
    description: string;
    category_id: number | null;
    type: ProductType;
    price: number;
    price_type: PriceType;
    billing_interval: BillingInterval | null;
    billing_interval_count: number | null;
    trial_period_days: number | null;
    stock_quantity: number | null;
    track_inventory: boolean;
    is_active: boolean;
    image: File | null;
};

export type ProductCategory = { id: number; name: string };

type Props = {
    data: ProductFormData;
    setData: <K extends keyof ProductFormData>(key: K, value: ProductFormData[K]) => void;
    errors: Partial<Record<keyof ProductFormData, string>>;
    categories: ProductCategory[];
    existingImageUrl?: string | null;
};

const billingIntervalLabels: Record<BillingInterval, string> = {
    weekly: 'Weekly',
    monthly: 'Monthly',
    yearly: 'Yearly',
};

export function ProductFormFields({ data, setData, errors, categories, existingImageUrl }: Props) {
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [priceDisplay, setPriceDisplay] = useState(data.price > 0 ? (data.price / 100).toFixed(2) : '');
    const fileInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    function handleTypeChange(value: ProductType) {
        setData('type', value);

        if (value === 'subscription') {
            setData('price_type', 'recurring');

            if (!data.billing_interval) setData('billing_interval', 'monthly');

            if (!data.billing_interval_count) setData('billing_interval_count', 1);
        } else {
            setData('price_type', 'one_time');
            setData('billing_interval', null);
            setData('billing_interval_count', null);
            setData('trial_period_days', null);
        }

        if (value !== 'physical') {
            setData('track_inventory', false);
            setData('stock_quantity', null);
        }
    }

    function handleImageChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;

        if (previewUrl) URL.revokeObjectURL(previewUrl);

        setData('image', file);
        setPreviewUrl(file ? URL.createObjectURL(file) : null);
    }

    const displayImageUrl = previewUrl ?? existingImageUrl ?? null;
    const isSubscription = data.type === 'subscription';
    const isPhysical = data.type === 'physical';

    return (
        <>
            {/* Product Details */}
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Product name"
                        autoComplete="off"
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="sku">SKU</Label>
                    <Input
                        id="sku"
                        name="sku"
                        value={data.sku}
                        onChange={(e) => setData('sku', e.target.value.toUpperCase())}
                        placeholder="AB-1234"
                        autoComplete="off"
                        required
                    />
                    <InputError message={errors.sku} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <Input
                    id="description"
                    name="description"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    placeholder="Brief product description"
                    autoComplete="off"
                    required
                />
                <InputError message={errors.description} />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="category_id">Category</Label>
                    <Select
                        value={data.category_id?.toString() ?? 'none'}
                        onValueChange={(value) =>
                            setData('category_id', value === 'none' ? null : Number(value))
                        }
                    >
                        <SelectTrigger id="category_id">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No category</SelectItem>
                            {categories.map((cat) => (
                                <SelectItem key={cat.id} value={cat.id.toString()}>
                                    {cat.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.category_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="type">Type</Label>
                    <Select value={data.type} onValueChange={(v) => handleTypeChange(v as ProductType)}>
                        <SelectTrigger id="type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="physical">Physical</SelectItem>
                            <SelectItem value="digital">Digital</SelectItem>
                            <SelectItem value="subscription">Subscription</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.type} />
                </div>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="is_active"
                    checked={data.is_active}
                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                />
                <Label htmlFor="is_active">Active</Label>
            </div>

            {/* Pricing */}
            <div className="grid gap-2">
                <Label htmlFor="price">Price</Label>
                <div className="relative max-w-48">
                    <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground">
                        $
                    </span>
                    <Input
                        id="price"
                        name="price"
                        type="text"
                        inputMode="decimal"
                        className="pl-6"
                        placeholder="0.00"
                        value={priceDisplay}
                        onChange={(e) => {
                            const val = e.target.value;
                            // Extra decimal protection
                            if (!/^\d*\.?\d*$/.test(val)) return;

                            setPriceDisplay(val);
                            const parsed = parseFloat(val);
                            setData('price', isNaN(parsed) ? 0 : Math.round(parsed * 100));
                        }}
                        onBlur={() => {
                            setPriceDisplay(data.price > 0 ? (data.price / 100).toFixed(2) : '');
                        }}
                        required
                    />
                </div>
                <InputError message={errors.price} />
            </div>

            {/* Subscription billing fields */}
            {isSubscription && (
                <>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="billing_interval">Billing Interval</Label>
                            <Select
                                value={data.billing_interval ?? 'monthly'}
                                onValueChange={(v) => setData('billing_interval', v as BillingInterval)}
                            >
                                <SelectTrigger id="billing_interval">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {(Object.keys(billingIntervalLabels) as BillingInterval[]).map(
                                        (interval) => (
                                            <SelectItem key={interval} value={interval}>
                                                {billingIntervalLabels[interval]}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.billing_interval} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="billing_interval_count">Every</Label>
                            <Input
                                id="billing_interval_count"
                                name="billing_interval_count"
                                type="number"
                                min="1"
                                value={data.billing_interval_count ?? 1}
                                onChange={(e) =>
                                    setData(
                                        'billing_interval_count',
                                        parseInt(e.target.value) || 1,
                                    )
                                }
                            />
                            <InputError message={errors.billing_interval_count} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="trial_period_days">
                            Trial Period{' '}
                            <span className="text-muted-foreground font-normal">(days, optional)</span>
                        </Label>
                        <Input
                            id="trial_period_days"
                            name="trial_period_days"
                            type="number"
                            min="0"
                            className="max-w-48"
                            value={data.trial_period_days ?? ''}
                            onChange={(e) =>
                                setData(
                                    'trial_period_days',
                                    e.target.value === '' ? null : parseInt(e.target.value),
                                )
                            }
                            placeholder="0"
                        />
                        <InputError message={errors.trial_period_days} />
                    </div>
                </>
            )}

            {/* Physical inventory fields */}
            {isPhysical && (
                <>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="track_inventory"
                            checked={data.track_inventory}
                            onCheckedChange={(checked) => {
                                const tracking = checked === true;
                                setData('track_inventory', tracking);

                                if (!tracking) setData('stock_quantity', null);
                            }}
                        />
                        <Label htmlFor="track_inventory">Track inventory</Label>
                    </div>

                    {data.track_inventory && (
                        <div className="grid gap-2">
                            <Label htmlFor="stock_quantity">Stock Quantity</Label>
                            <Input
                                id="stock_quantity"
                                name="stock_quantity"
                                type="number"
                                min="0"
                                className="max-w-48"
                                value={data.stock_quantity ?? ''}
                                onChange={(e) =>
                                    setData(
                                        'stock_quantity',
                                        e.target.value === '' ? null : parseInt(e.target.value),
                                    )
                                }
                                required
                            />
                            <InputError message={errors.stock_quantity} />
                        </div>
                    )}
                </>
            )}

            {/* Image */}
            <div className="grid gap-2">
                <Label htmlFor="image">
                    Image <span className="text-muted-foreground font-normal">(optional)</span>
                </Label>
                {displayImageUrl && (
                    <img
                        src={displayImageUrl}
                        alt="Product preview"
                        className="h-32 w-32 rounded-md object-cover"
                    />
                )}
                <input
                    ref={fileInputRef}
                    id="image"
                    name="image"
                    type="file"
                    accept="image/*"
                    className="text-sm file:mr-3 file:cursor-pointer file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-accent"
                    onChange={handleImageChange}
                />
                <InputError message={errors.image} />
            </div>
        </>
    );
}
