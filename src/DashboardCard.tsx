/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.1
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

import React from 'react';
import Card from '@mui/material/Card';
import CardHeader from '@mui/material/CardHeader';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import { CardStatus, CardStatusInfo } from './dashboard_utils';

interface DashboardCardProps {
    cardIcon: JSX.Element;
    cardTitle: string;
    cardContent: JSX.Element;
    cardStatus: CardStatus;
}

export default function DashboardCard({ cardIcon, cardTitle, cardContent, cardStatus }: DashboardCardProps) {
    const { headerBgColor, headerAriaLabel } = CardStatusInfo[cardStatus];
    return (
        <Card sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
            <CardHeader
                title={
                    <Stack direction='row' alignItems='center' gap={1} fontSize='1.05rem'>
                        {cardIcon} {cardTitle}
                    </Stack>
                }
                titleTypographyProps={{ align: 'center' }}
                sx={{ backgroundColor: headerBgColor }}
                aria-label={headerAriaLabel}
            />
            <CardContent sx={{ display: 'flex', justifyContent: 'center', flexGrow: '1' }}>
                {cardContent}
            </CardContent>
        </Card>
    );
}
