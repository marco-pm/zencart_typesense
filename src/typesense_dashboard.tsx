/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

import React from 'react'
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createTheme, ThemeProvider } from '@mui/material/styles';
import { Container } from '@mui/material';
import { grey } from '@mui/material/colors';
import Box from '@mui/material/Box';
import Grid from '@mui/material/Unstable_Grid2';
import ScopedCssBaseline from '@mui/material/ScopedCssBaseline';
import Typography from '@mui/material/Typography';
import CardServerStatus from './CardServerStatus';
import CardSyncStatus from './CardSyncStatus';
import CardCollections from './CardCollections';

declare const typesenseI18n: { [key: string]: string };

const theme = createTheme({
    palette: {
        primary: grey,
        secondary: {
            main: grey[200],
        },
    },
    components: {
        MuiCardContent: {
            styleOverrides: {
                root: {
                    padding: '1.5rem',
                    '&:last-child': {
                        paddingBottom: '1.5rem',
                    },
                },
            },
        },
        MuiTableCell: {
            styleOverrides: {
                root: {
                    padding: '8px 0',
                }
            }
        }
    },
});

const queryClient = new QueryClient();

const Dashboard = () => {
    return (
        <React.StrictMode>
            <QueryClientProvider client={queryClient}>
                <ScopedCssBaseline>
                    <ThemeProvider theme={theme}>
                        <Container sx={{ marginBottom: 5 }}>
                            <Typography
                                component='h1'
                                variant='h5'
                                textAlign='center'
                                mt={4}
                                mb={5}
                                fontWeight={700}
                                sx={{ fontVariant: 'none' }}
                            >
                                {typesenseI18n['TYPESENSE_DASHBOARD_TITLE']}
                            </Typography>
                            <Grid container spacing={4} alignItems='stretch'>
                                <Grid xs={12} md={6}>
                                    <CardServerStatus />
                                </Grid>
                                <Grid xs={12} md={6}>
                                    <CardSyncStatus />
                                </Grid>
                                <Grid xs={12} md={6}>
                                    <CardCollections />
                                </Grid>
                                <Grid xs={12} md={6}>
                                    <Box>temp</Box>
                                </Grid>
                            </Grid>
                        </Container>
                    </ThemeProvider>
                </ScopedCssBaseline>
            </QueryClientProvider>
        </React.StrictMode>
    );
}

const container = document.getElementById('main');
if (container) {
    const root = createRoot(container);
    root.render(<Dashboard />);
}
