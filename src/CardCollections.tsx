import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchData, ErrorMessageBox, CardStatus } from './dashboard_utils';
import DashboardCard from './DashboardCard';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import LayersIcon from '@mui/icons-material/Layers';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';

declare const typesenseI18n: { [key: string]: string };

interface CollectionNoOfDocuments {
    alias_name: string;
    num_documents: number;
}

export default function CardCollections () {
    const collectionsQuery = useQuery({
        queryKey: ['collections'],
        queryFn: async () => fetchData<CollectionNoOfDocuments[]>('getCollectionsNoOfDocuments', false),
        retry: false,
        refetchOnWindowFocus: false
    });

    let cardContent: JSX.Element =
        <Box textAlign='center'>
            <CircularProgress sx={{ my: 2 }}/>
        </Box>;
    let cardStatus = CardStatus.LOADING;

    if (!collectionsQuery.isLoading) {
        if (collectionsQuery.isError || !collectionsQuery.data) {
            console.log(collectionsQuery.error);
            cardStatus = CardStatus.WARNING;
            cardContent = <ErrorMessageBox
                messageLine1={typesenseI18n['TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_1']}
                messageLine2={typesenseI18n['TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_2']}
            />;
        } else if (collectionsQuery.data.length === 0) {
            cardStatus = CardStatus.ERROR;
            cardContent = <ErrorMessageBox
                messageLine1={typesenseI18n['TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_NO_COLLECTIONS_1']}
                messageLine2={typesenseI18n['TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_NO_COLLECTIONS_2']}
            />;
        } else if (collectionsQuery.data.length < 3) {
            cardStatus = CardStatus.ERROR;
            cardContent = <ErrorMessageBox
                messageLine1={typesenseI18n['TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_COLLECTIONS_MISSING_1']}
                messageLine2={typesenseI18n['TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_COLLECTIONS_MISSING_2']}
            />;
        } else {
            const productsCollection = collectionsQuery.data.find(collection => collection.alias_name === 'products');
            if (productsCollection && productsCollection.num_documents === 0) {
                cardStatus = CardStatus.ERROR;
                cardContent = <ErrorMessageBox
                    messageLine1={typesenseI18n['TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_PRODUCTS_COLLECTION_EMPTY_1']}
                    messageLine2={typesenseI18n['TYPESENSE_DASHBOARD_CARD_COLLECTIONS_ERROR_PRODUCTS_COLLECTION_EMPTY_2']}
                />;
            } else {
                cardStatus = CardStatus.OK;
                cardContent =
                    <TableContainer>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    <TableCell>
                                        {typesenseI18n['TYPESENSE_DASHBOARD_CARD_COLLECTIONS_HEADING_COLLECTION']}
                                    </TableCell>
                                    <TableCell align='right'>
                                        {typesenseI18n['TYPESENSE_DASHBOARD_CARD_COLLECTIONS_HEADING_NO_DOCUMENTS']}
                                    </TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {collectionsQuery.data.map(collection => (
                                    <TableRow key={collection.alias_name}>
                                        <TableCell component='th' scope='row'>
                                            {collection.alias_name}
                                        </TableCell>
                                        <TableCell align='right'>
                                            {collection.num_documents}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </TableContainer>
            }
        }
    }

    return (
        <DashboardCard
            cardIcon={<LayersIcon sx={{ fontSize:'1.2rem' }} />}
            cardTitle={typesenseI18n['TYPESENSE_DASHBOARD_CARD_COLLECTIONS_TITLE']}
            cardContent={cardContent}
            cardStatus={cardStatus}
        />
    );
}
