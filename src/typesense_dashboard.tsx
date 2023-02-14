/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

import React from 'react'
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createTheme, ThemeProvider } from '@mui/material/styles';
import { Container } from "@mui/material";
import { grey } from "@mui/material/colors";
import Box from '@mui/material/Box';
import Grid from '@mui/material/Unstable_Grid2';
import ScopedCssBaseline from '@mui/material/ScopedCssBaseline';
import Typography from '@mui/material/Typography';
import CardServerStatus from "./CardServerStatus";

declare const typesenseI18n: { [key: string]: string };

const theme = createTheme({
    palette: {
        primary: grey,
    },
    components: {
        MuiCardContent: {
            styleOverrides: {
                root: {
                    padding: '1.5em',
                    "&:last-child": {
                        paddingBottom: '1.5em',
                    },
                },
            },
        },
    },
});

const queryClient = new QueryClient();

const Dashboard = () => {
    return (
        <React.StrictMode>
            <QueryClientProvider client={queryClient}>
                <ScopedCssBaseline>
                    <ThemeProvider theme={theme}>
                        <Container sx={{ fontSize: 16, marginBottom: 5 }}>
                            <Typography
                                component="h1"
                                variant="h4"
                                textAlign="center"
                                mt={3}
                                mb={5}
                                fontWeight={700}
                                sx={{ fontVariant: 'none' }}
                            >
                                {typesenseI18n['TYPESENSE_DASHBOARD_TITLE']}
                            </Typography>
                            <Grid container spacing={4}>
                                <Grid xs={12} sm={6}>
                                    <CardServerStatus />
                                </Grid>
                                <Grid xs={12} sm={6}>
                                    <Box>suca</Box>
                                </Grid>
                                <Grid xs={12} sm={6}>
                                    <Box>suca</Box>
                                </Grid>
                                <Grid xs={12} sm={6}>
                                    <Box>suca</Box>
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
