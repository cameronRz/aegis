import { formatCents } from '@/lib/money';
import { productTypeLabels } from '@/lib/product-type';
import type { OrderItem } from '@/types';

type Props = {
    items: OrderItem[];
    total: number;
};

export function OrderItemsTable({ items, total }: Props) {
    return (
        <div className="rounded-lg border">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b">
                        <th className="px-4 py-3 text-left font-medium">Item</th>
                        <th className="px-4 py-3 text-left font-medium">Type</th>
                        <th className="px-4 py-3 text-right font-medium">Unit price</th>
                        <th className="px-4 py-3 text-right font-medium">Qty</th>
                        <th className="px-4 py-3 text-right font-medium">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((item) => (
                        <tr key={item.id} className="border-b last:border-0">
                            <td className="px-4 py-3">
                                <div className="font-medium">{item.product_name}</div>
                                <div className="text-muted-foreground">{item.product_sku}</div>
                            </td>
                            <td className="text-muted-foreground px-4 py-3">
                                {productTypeLabels[item.product_type] ?? item.product_type}
                            </td>
                            <td className="px-4 py-3 text-right tabular-nums">{formatCents(item.price)}</td>
                            <td className="px-4 py-3 text-right tabular-nums">{item.quantity}</td>
                            <td className="px-4 py-3 text-right tabular-nums">
                                {formatCents(item.price * item.quantity)}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t">
                        <td colSpan={4} className="px-4 py-3 font-semibold">
                            Total
                        </td>
                        <td className="px-4 py-3 text-right font-semibold tabular-nums">
                            {formatCents(total)}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}
