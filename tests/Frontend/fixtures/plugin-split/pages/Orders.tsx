import { useState } from 'react';
import { Button } from '@imajiner/admin-sdk/ui';
import { loads } from '../state';
import { total } from './money';

loads.push('orders');

export default function OrdersIndex() {
    const [count, setCount] = useState(0);

    return <Button onClick={() => setCount(count + 1)}>Orders {count} {total(loads.length)}</Button>;
}
