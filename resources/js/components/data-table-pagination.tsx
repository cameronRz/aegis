import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { PaginatedData } from '@/types';

type Props<T> = {
    paginatedData: PaginatedData<T>;
};

export function DataTablePagination<T>({ paginatedData }: Props<T>) {
    const pageLinks = paginatedData.links.filter(
        (link) => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;',
    );

    function goToPage(url: string | null) {
        if (!url) return;

        router.get(url, {}, { preserveState: true });
    }

    return (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
                {paginatedData.from
                    ? `Showing ${paginatedData.from}–${paginatedData.to} of ${paginatedData.total}`
                    : 'No results'}
            </span>
            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!paginatedData.prev_page_url}
                    onClick={() => goToPage(paginatedData.prev_page_url)}
                >
                    Previous
                </Button>
                {pageLinks.map((link) => (
                    <Button
                        key={link.label}
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        disabled={!link.url}
                        onClick={() => goToPage(link.url)}
                    >
                        {link.label}
                    </Button>
                ))}
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!paginatedData.next_page_url}
                    onClick={() => goToPage(paginatedData.next_page_url)}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}
