import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { definePlugin } from '@imajiner/admin-sdk';
import { Button } from '@imajiner/admin-sdk/ui';

function OrdersIndex() {
    const [count, setCount] = useState(0);
    usePage();

    return <Button onClick={() => setCount(count + 1)}>Orders {count}</Button>;
}

definePlugin({ pages: { 'Shop/Orders/Index': OrdersIndex } });
