/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

import React, { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { CardStatus, ErrorMessageBox, fetchData, LoadingBox } from './dashboard_utils';
import parse from 'html-react-parser';
import DashboardCard from './DashboardCard';
import SyncIcon from '@mui/icons-material/Sync';
import Stack from '@mui/material/Stack';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';

declare const typesenseI18n: { [key: string]: string };
declare const typesenseSyncAfterFailed: boolean;
declare const typesenseSyncTimeoutMinutes: number;
declare const typesenseFullSyncFrequencyHours: number;

interface SyncStatus {
    status: string;
    is_next_run_full: string;
    last_incremental_sync_end_time: string;
    last_full_sync_end_time: string;
    minutes_since_last_start: number;
    seconds_since_last_start: number;
    hours_since_last_full_sync: number;
}

export default function CardSyncStatus () {
    const [refetchInterval, setRefetchInterval] = useState(5000);

    const syncStatusQuery = useQuery({
        queryKey: ['syncstatus'],
        queryFn: async () => fetchData<SyncStatus>('getSyncStatus'),
        refetchInterval: refetchInterval,
        retry: false,
        onError: () => setRefetchInterval(0)
    });

    const setSyncFull = useMutation({
        mutationFn: async (isForced: boolean) => fetchData('setFullSyncGraceful', {isForced: isForced}),
        onSuccess: () => syncStatusQuery.refetch()
    });

    let cardContent = <LoadingBox />;
    let cardStatus = CardStatus.LOADING;

    if (!syncStatusQuery.isLoading) {
        if (syncStatusQuery.isError || !syncStatusQuery.data) {
            console.log(syncStatusQuery.error);
            cardStatus = CardStatus.WARNING;
            cardContent = <ErrorMessageBox
                messageLine1={typesenseI18n['TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_1']}
                messageLine2={typesenseI18n['TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_2']}
            />;
        } else {
            const isFirstSync =
                syncStatusQuery.data.last_incremental_sync_end_time === null &&
                syncStatusQuery.data.last_full_sync_end_time === null;
            const isNextRunFull = syncStatusQuery.data.is_next_run_full === '1';

            let cardText1 = '';
            let cardText2 = '';
            let gracefulSyncButtonDisabled = true;
            let forceSyncButtonDisabled = true;

            if (isFirstSync) {
                cardStatus = CardStatus.WARNING;
                cardText1 = typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FIRST_SYNC_TEXT'];
            } else {
                switch (syncStatusQuery.data.status) {
                case 'failed':
                    cardStatus = CardStatus.ERROR;
                    cardText1 = typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FAILED_TEXT_1'];
                    cardText2 = typesenseSyncAfterFailed
                        ? typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FAILED_TEXT_2_RETRY_SYNC']
                        : typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FAILED_TEXT_2_NO_RETRY_SYNC'];
                    if (typesenseSyncAfterFailed) {
                        gracefulSyncButtonDisabled = false;
                    }
                    forceSyncButtonDisabled = false;
                    break;
                case 'running':
                    cardText1 = typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_RUNNING_TEXT_1'];
                    cardText1 += typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_RUNNING_TEXT_1_SUFFIX']
                        .replace('%s', syncStatusQuery.data.seconds_since_last_start.toString());

                    if (syncStatusQuery.data.minutes_since_last_start > typesenseSyncTimeoutMinutes + 5) {
                        cardStatus = CardStatus.ERROR;
                        cardText2 = typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_RUNNING_OVER_TIMEMOUT_TEXT_2'];
                    } else {
                        cardStatus = CardStatus.OK;
                    }
                    forceSyncButtonDisabled = false;
                    break;
                case 'completed':
                    cardText1 = typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_COMPLETED_TEXT_1'];
                    if (syncStatusQuery.data.hours_since_last_full_sync > typesenseFullSyncFrequencyHours + 1) {
                        cardStatus = CardStatus.ERROR;
                        cardText2 = typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_COMPLETED_OVER_FULL_FREQUENCY_2'];
                    } else {
                        cardStatus = CardStatus.OK;
                    }
                    gracefulSyncButtonDisabled = false;
                    forceSyncButtonDisabled = false;
                    break;
                }
            }

            cardContent =
                <Stack width='100%' spacing={1}>
                    <Box>
                        {parse(cardText1)}
                    </Box>
                    <Box>
                        {parse(cardText2)}
                    </Box>
                    {isNextRunFull && (
                        <Box>
                            {parse(typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_NEXT_RUN_FULL_TEXT'])}
                        </Box>
                    )}
                    <Stack direction='row' justifyContent='space-between' pt={2} fontSize='0.85rem'>
                        <Box>
                            {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_LAST_INCREMENTAL_SYNC_TEXT']}
                        </Box>
                        <Box>
                            {syncStatusQuery.data.last_incremental_sync_end_time ?? 'never'}
                        </Box>
                    </Stack>
                    <Stack direction='row' justifyContent='space-between' fontSize='0.85rem'>
                        <Box>
                            {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_LAST_FULL_SYNC_TEXT']}
                        </Box>
                        <Box>
                            {syncStatusQuery.data.last_full_sync_end_time ?? 'never'}
                        </Box>
                    </Stack>
                    <Stack pt={2} direction='row' spacing={2} justifyContent='center'>
                        <Button
                            variant='contained'
                            color='secondary'
                            disabled={isNextRunFull || gracefulSyncButtonDisabled}
                            onClick={() => setSyncFull.mutate(false)}
                        >
                            {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_GRACEFUL_SYNC_BUTTON_TEXT']}
                        </Button>
                        <Button
                            variant='contained'
                            color='secondary'
                            disabled={forceSyncButtonDisabled}
                            onClick={() => setSyncFull.mutate(true)}
                        >
                            {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_FORCE_SYNC_BUTTON_TEXT']}
                        </Button>
                    </Stack>
                </Stack>;
        }
    }

    return (
        <DashboardCard
            cardIcon={<SyncIcon sx={{ fontSize:'1.2rem' }} />}
            cardTitle={typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNC_STATUS_TITLE']}
            cardContent={cardContent}
            cardStatus={cardStatus}
        />
    );
}
