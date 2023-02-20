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
import CircularProgress from '@mui/material/CircularProgress';
import { SynonymSchema } from 'typesense/lib/Typesense/Synonym';

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

export enum ZencartCollection {
    PRODUCTS = 'products',
    CATEGORIES = 'categories',
    BRANDS = 'brands',
}

interface SetFullSyncParam {
    isForced: boolean;
}

export interface SynonymSchemaWithCollection extends SynonymSchema {
    collection: ZencartCollection;
}

type FetchDataParameter = SetFullSyncParam | SynonymSchemaWithCollection;

type OptionsType = {
    method: string;
    headers: {
        'Content-Type': string;
        'X-Requested-With': string;
    };
    body?: string;
};

export async function fetchData<T>(ajaxMethod: string, parameters?: FetchDataParameter): Promise<T> {
    let url = `ajax.php?act=ajaxAdminTypesenseDashboard&method=${ajaxMethod}`;

    if (parameters) {
        const params = new URLSearchParams();
        Object.entries(parameters).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                // Must pass the array as a string because ajax.php does not support arrays as $_GET parameters
                const stringValues = value.map(item => String(item).replace(/\|/g, '')); // Remove | characters
                params.append(key, stringValues.join('|'));
            } else {
                params.append(key, String(value));
            }
        });
        url += `&${params.toString()}`;
    }

    const options: OptionsType = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    const response = await fetch(url, options);

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

export function LoadingBox() {
    return (
        <Box textAlign='center'>
            <CircularProgress sx={{ my: 2 }}/>
        </Box>
    );
}
