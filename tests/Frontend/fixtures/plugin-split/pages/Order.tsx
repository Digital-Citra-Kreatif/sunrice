import { usePage } from '@inertiajs/react';
import { total } from './money';

export function OrderShow() {
    usePage();

    return <p>{total(1)}</p>;
}
