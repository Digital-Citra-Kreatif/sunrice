import { definePlugin } from '@imajiner/admin-sdk';
import { loads } from './state';

loads.push('entry');

definePlugin({
    lazyPages: {
        'Shop/Orders/Index': () => import('./pages/Orders'),
        'Shop/Orders/Show': () => import('./pages/Order').then((module) => module.OrderShow),
    },
});
