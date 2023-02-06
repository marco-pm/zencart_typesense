/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

import React from 'react'
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider, useQuery } from "@tanstack/react-query";
import { ChakraProvider } from '@chakra-ui/react';
import { extendTheme } from "@chakra-ui/react";
import { Card, CardHeader, CardBody, Heading, Icon, SimpleGrid, Text, Spinner, Progress, Box } from '@chakra-ui/react';
import { BiServer } from 'react-icons/bi';
import { HealthResponse } from 'typesense/lib/Typesense/Health';
import { MetricsResponse } from 'typesense/lib/Typesense/Metrics';

const theme = extendTheme({
    fonts: {
        body: "Roboto, sans-serif",
    },
})

const queryClient = new QueryClient();

async function fetchData<T>(ajaxMethod: string, isPost: boolean): Promise<T> {
    const response = await fetch(`ajax.php?act=ajaxAdminTypesenseDashboard&method=${ajaxMethod}`, {
        method: isPost ? 'POST' : 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    return await response.json() as T;
}

const CardServerStatus = () => {
    const healthQuery = useQuery({
        queryKey: ['health'],
        queryFn: async () => fetchData<HealthResponse>('getHealth', false),
    });

    const metricsQuery = useQuery({
        queryKey: ['metrics'],
        queryFn: async () => fetchData<MetricsResponse>('getMetrics', false),
        refetchInterval: 5000,
    });

    let cardContent: JSX.Element = <Spinner />;
    let bgColor = 'white';
    if (!healthQuery.isLoading && !metricsQuery.isLoading) {
        if (healthQuery.isError || !healthQuery.data || metricsQuery.isError || !metricsQuery.data) {
            console.log(healthQuery.error);
            bgColor = 'yellow.50';
            cardContent = <Text>Error while retrieving data</Text>;
        } else if (!healthQuery.data.ok) {
            bgColor = 'red.100';
            cardContent = <Text>Server not OK</Text>;
        } else {
            console.log(metricsQuery.data);
            bgColor = 'green.50';
            cardContent =
                <>
                    <Box>
                        <strong>OK</strong>
                    </Box>
                    <Box mt={4}>
                        Memory usage: {metricsQuery.data.system_disk_used_bytes}
                        <Progress value={parseInt(metricsQuery.data.system_disk_used_bytes) / parseInt(metricsQuery.data.system_disk_total_bytes) * 100} />
                    </Box>
                </>;
        }
    }

    return (
        <Card bg={bgColor}>
            <CardHeader>
                <Heading display='flex' alignItems='center' columnGap={2}>
                    <Icon as={BiServer} /> Server status
                </Heading>
            </CardHeader>
            <CardBody>
                {cardContent}
            </CardBody>
        </Card>
    );
}

const Dashboard = () => {
    return (
        <React.StrictMode>
            <QueryClientProvider client={queryClient}>
                <ChakraProvider theme={theme}>
                    <Heading mb={10} textAlign='center'>Typesense Dashboard</Heading>
                    <SimpleGrid columns={[1, null, 2]} spacing={10} p={4} fontSize='1.3rem'>
                        <CardServerStatus />
                    </SimpleGrid>
                </ChakraProvider>
            </QueryClientProvider>
        </React.StrictMode>
    );
}

const container = document.getElementById('main');
if (container) {
    const root = createRoot(container);
    root.render(<Dashboard />);
}
