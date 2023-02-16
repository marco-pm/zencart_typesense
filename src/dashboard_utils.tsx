/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

import React from 'react';
import Box from '@mui/material/Box';
import { amber, grey, lightGreen, red } from '@mui/material/colors';

export enum CardStatus {
    LOADING = 'loading',
    OK = 'ok',
    WARNING = 'warning',
    ERROR = 'error',
}

export const CardStatusInfo = {
    [CardStatus.LOADING]: {
        headerBgColor: grey[300],
        headerAriaLabel: 'Loading...',
    },
    [CardStatus.OK]: {
        headerBgColor: lightGreen[300],
        headerAriaLabel: 'OK',
    },
    [CardStatus.WARNING]: {
        headerBgColor: amber[200],
        headerAriaLabel: 'Warning',
    },
    [CardStatus.ERROR]: {
        headerBgColor: red[300],
        headerAriaLabel: 'Error',
    },
};

export async function fetchData<T>(ajaxMethod: string, isPost: boolean): Promise<T> {
    const response = await fetch(`ajax.php?act=ajaxAdminTypesenseDashboard&method=${ajaxMethod}`, {
        method: isPost ? 'POST' : 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    return await response.json() as T;
}

export function ErrorMessageBox({ messageLine1, messageLine2 }: { messageLine1: string, messageLine2: string }) {
    return (
        <Box display='flex' flexDirection='column' alignItems='center' justifyContent='center'>
            <p><strong>{messageLine1}</strong></p>
            <p>{messageLine2}</p>
        </Box>
    );
}
