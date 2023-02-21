/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchData, ErrorMessageBox, CardStatus, LoadingBox } from './dashboard_utils';
import { HealthResponse } from 'typesense/lib/Typesense/Health';
import { MetricsResponse } from 'typesense/lib/Typesense/Metrics';
import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import StorageIcon from '@mui/icons-material/Storage';
import LinearProgress from '@mui/material/LinearProgress';
import DashboardCard from './DashboardCard';

declare const typesenseI18n: { [key: string]: string };

export default function CardServerStatus () {
    const [refetchInterval, setRefetchInterval] = React.useState(5000);

    const healthQuery = useQuery({
        queryKey: ['health'],
        queryFn: async () => fetchData<HealthResponse>('getHealth'),
        retry: false,
        refetchOnWindowFocus: false
    });

    const metricsQuery = useQuery({
        queryKey: ['metrics'],
        queryFn: async () => fetchData<MetricsResponse>('getMetrics'),
        refetchInterval: refetchInterval,
        retry: false,
        refetchOnWindowFocus: false,
        onError: () => setRefetchInterval(0)
    });

    function convertToGB(bytesStr: string): number {
        const bytes = parseInt(bytesStr);
        const gigabytes = bytes / 1000 / 1000 / 1000;
        return Number(gigabytes.toFixed(2));
    }

    let cardContent = <LoadingBox />;
    let cardStatus = CardStatus.LOADING;

    if (!healthQuery.isLoading && !metricsQuery.isLoading) {
        if (healthQuery.isError || !healthQuery.data || metricsQuery.isError || !metricsQuery.data) {
            console.log(healthQuery.error);
            cardStatus = CardStatus.WARNING;
            cardContent = <ErrorMessageBox
                messageLine1={typesenseI18n['TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_1']}
                messageLine2={typesenseI18n['TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_2']}
            />;
        } else {
            cardStatus = healthQuery.data.ok ? CardStatus.OK : CardStatus.ERROR;
            const diskUsedGb = convertToGB(metricsQuery.data.system_disk_used_bytes);
            const diskTotalGb = convertToGB(metricsQuery.data.system_disk_total_bytes);
            const diskUsedPercent = Math.round(diskUsedGb / diskTotalGb * 100);
            const memoryUsedGb = convertToGB(metricsQuery.data.system_memory_used_bytes);
            const memoryTotalGb = convertToGB(metricsQuery.data.system_memory_total_bytes);
            const memoryUsedPercent = Math.round(memoryUsedGb / memoryTotalGb * 100);

            cardContent =
                <Stack width='100%'>
                    <Box pb={1}>
                        <strong>{healthQuery.data.ok
                            ? typesenseI18n['TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_OK']
                            : typesenseI18n['TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_ERROR']
                        }</strong>
                    </Box>
                    <Box mt={2} fontSize='0.8em'>
                        {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_MEMORY_USAGE']}: {memoryUsedPercent}%
                    </Box>
                    <Box sx={{ display: 'flex', alignItems: 'center', columnGap: 2, marginTop: '0.1em'  }}>
                        <Box sx={{ width: '75%' }}>
                            <LinearProgress variant='determinate' value={memoryUsedPercent} sx={{ height: 12 }}/>
                        </Box>
                        <Box sx={{ fontSize: '0.8em' }}>
                            {memoryUsedGb.toFixed(2)} / {memoryTotalGb.toFixed(2)} GB
                        </Box>
                    </Box>
                    <Box mt={1} fontSize='0.8em'>
                        {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_DISK_USAGE']}: {diskUsedPercent}%
                    </Box>
                    <Box sx={{ display: 'flex', alignItems: 'center', columnGap: 2, marginTop: '0.1em' }}>
                        <Box sx={{ width: '75%' }}>
                            <LinearProgress variant='determinate' value={diskUsedPercent} sx={{ height: 12 }}/>
                        </Box>
                        <Box sx={{ fontSize: '0.8em' }}>
                            {diskUsedGb.toFixed(2)} / {diskTotalGb.toFixed(2)} GB
                        </Box>
                    </Box>
                </Stack>;
        }
    }

    return (
        <DashboardCard
            cardIcon={<StorageIcon sx={{ fontSize:'1.05rem' }} />}
            cardTitle={typesenseI18n['TYPESENSE_DASHBOARD_CARD_SERVER_STATUS_TITLE']}
            cardContent={cardContent}
            cardStatus={cardStatus}
        />
    );
}
