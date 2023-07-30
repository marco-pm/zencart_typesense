/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.2
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

import React, { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { CardStatus,
    ErrorMessageBox,
    fetchData,
    LoadingBox,
    SynonymSchemaWithCollection,
    ZencartCollection
} from './dashboard_utils';
import { SynonymSchema } from 'typesense/lib/Typesense/Synonym';
import { MuiChipsInput } from 'mui-chips-input';
import AbcIcon from '@mui/icons-material/Abc';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import DashboardCard from './DashboardCard';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import FormControl from '@mui/material/FormControl';
import FormControlLabel from '@mui/material/FormControlLabel';
import FormLabel from '@mui/material/FormLabel';
import IconButton from '@mui/material/IconButton';
import Pagination from '@mui/material/Pagination';
import Radio from '@mui/material/Radio';
import RadioGroup from '@mui/material/RadioGroup';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';

declare const typesenseI18n: { [key: string]: string };

const SynonymForm = ({synonym, handleSave, handleCancel}: {
    synonym: SynonymSchema;
    handleSave: (synonym: SynonymSchemaWithCollection) => void;
    handleCancel: () => void;
}) => {
    const [collection, setCollection] = useState<ZencartCollection>(ZencartCollection.PRODUCTS);
    const [root, setRoot] = useState(synonym.root);
    const [synonyms, setSynonyms] = useState(synonym.synonyms);

    return (
        <Stack width='100%' spacing={1} pb={2}>
            <FormControl>
                <FormLabel id='synonym-collection-label'>
                    {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_COLLECTION_LABEL']}
                </FormLabel>
                <RadioGroup
                    row
                    aria-labelledby='synonym-collection-label'
                    name='synonym-collection'
                    value={collection}
                    onChange={(event) => setCollection(event.target.value as ZencartCollection)}
                >
                    <FormControlLabel
                        value={ZencartCollection.PRODUCTS}
                        control={<Radio />}
                        label={ZencartCollection.PRODUCTS}
                        disabled={synonym.id !== '0'}
                    />
                    <FormControlLabel
                        value={ZencartCollection.CATEGORIES}
                        control={<Radio />}
                        label={ZencartCollection.CATEGORIES}
                        disabled={synonym.id !== '0'}
                    />
                    <FormControlLabel
                        value={ZencartCollection.BRANDS}
                        control={<Radio />}
                        label={ZencartCollection.BRANDS}
                        disabled={synonym.id !== '0'}
                    />
                </RadioGroup>
            </FormControl>
            <MuiChipsInput
                placeholder={typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_FORM_SYNONYMS_PLACEHOLDER']}
                label={typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_SYNONYMS_LABEL']}
                variant='outlined'
                fullWidth
                value={synonyms}
                onChange={(newSynonyms) => setSynonyms(newSynonyms)}
            />
            <TextField
                label={typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_ROOT_LABEL']}
                variant='outlined'
                fullWidth
                sx={{ marginTop: '1rem !important' }}
                value={root}
                onChange={(event) => setRoot(event.target.value)}
            />
            <Stack pt={2} direction='row' spacing={2}>
                <Button
                    variant='contained'
                    color='secondary'
                    onClick={() => handleSave({ ...synonym, collection, root, synonyms })}
                >
                    {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_FORM_SAVE_BUTTON_TEXT']}
                </Button>
                <Button
                    variant='contained'
                    color='secondary'
                    onClick={() => handleCancel()}
                >
                    {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_FORM_CANCEL_BUTTON_TEXT']}
                </Button>
            </Stack>
        </Stack>
    );
};

const SynonymsList = ({synonyms, handleEdit, handleDelete}: {
    synonyms: SynonymSchemaWithCollection[];
    handleEdit: (synonym: SynonymSchemaWithCollection) => void;
    handleDelete: (synonym: SynonymSchemaWithCollection) => void;
}) => {
    const [currentPage, setCurrentPage] = useState(1);
    const perPage = 10;

    const handlePageChange = (_event: React.ChangeEvent<unknown>, page: number) => {
        setCurrentPage(page);
    };

    const startIndex = (currentPage - 1) * perPage;
    const endIndex = startIndex + perPage;
    const currentSynonyms = synonyms.slice(startIndex, endIndex);

    if (synonyms.length === 0) {
        return (
            <Box py={3} textAlign='center'>
                {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_LIST_EMPTY']}
            </Box>
        );
    }

    return (
        <>
            <TableContainer sx={{ marginTop: '0.5rem' }}>
                <Table sx={{ minWidth: '400px' }}>
                    <TableHead>
                        <TableRow>
                            <TableCell>{typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_COLLECTION_LABEL']}</TableCell>
                            <TableCell sx={{ paddingLeft: '1rem', paddingRight: '1rem' }}>{typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_LIST_ROOT_LABEL']}</TableCell>
                            <TableCell>{typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_SYNONYMS_LABEL']}</TableCell>
                            <TableCell></TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {currentSynonyms.map((synonym) => (
                            <TableRow key={synonym.id}>
                                <TableCell>{synonym.collection}</TableCell>
                                <TableCell sx={{ paddingLeft: '1rem', paddingRight: '1rem' }}>{synonym.root}</TableCell>
                                <TableCell>{synonym.synonyms.join(' | ')}</TableCell>
                                <TableCell align='right' sx={{ padding: '0', whiteSpace: 'nowrap' }}>
                                    <IconButton
                                        aria-label={typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_LIST_EDIT_BUTTON_LABEL']}
                                        onClick={() => handleEdit(synonym)}
                                        sx={{ marginLeft: '0.5rem' }}
                                    >
                                        <EditIcon sx={{ fontSize:'1.2rem' }} />
                                    </IconButton>
                                    <IconButton
                                        aria-label={typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_LIST_DELETE_BUTTON_LABEL']}
                                        onClick={() => handleDelete(synonym)}
                                    >
                                        <DeleteIcon sx={{ fontSize:'1.2rem' }} />
                                    </IconButton>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>
            <Pagination
                count={Math.ceil(synonyms.length / perPage)}
                page={currentPage}
                onChange={handlePageChange}
                sx={{ display: 'flex', justifyContent: 'center' }}
            />
        </>
    );
};

export default function CardSynonyms () {
    const [synonyms, setSynonyms] = useState<SynonymSchemaWithCollection[]>([]);
    const [editingSynonym, setEditingSynonym] = useState<SynonymSchemaWithCollection | null>(null);

    const initialSynonym: SynonymSchemaWithCollection = {
        id: '0',
        root: '',
        synonyms: [],
        collection: ZencartCollection.PRODUCTS
    };

    const synonymsQuery = useQuery({
        queryKey: ['synonyms'],
        queryFn: async () => fetchData<SynonymSchemaWithCollection[]>('getSynonyms'),
        onSuccess: (synonyms) => setSynonyms(synonyms),
        retry: false,
        refetchOnWindowFocus: false
    });

    const upsertSynonym = useMutation({
        mutationFn: async (synonym: SynonymSchemaWithCollection) => fetchData<SynonymSchemaWithCollection>('upsertSynonym', synonym),
        onSuccess: (newSynonym, synonym) => {
            if (synonym.id && synonym.id !== '0') {
                // Update synonym
                setSynonyms((prevSynonyms) =>
                    prevSynonyms.map((prevSynonym) =>
                        prevSynonym.id === newSynonym.id ? newSynonym : prevSynonym
                    )
                );
            } else {
                // Add a new synonym
                setSynonyms((prevSynonyms) => [...prevSynonyms, newSynonym])
            }
        }
    });

    const deleteSynonym = useMutation({
        mutationFn: async (synonym: SynonymSchemaWithCollection) => fetchData<string>('deleteSynonym', synonym),
        onSuccess: (synonymID) => {
            setSynonyms((prevSynonyms) =>
                prevSynonyms.filter((prevSynonym) => prevSynonym.id !== synonymID)
            );
        }
    });

    const handleSave = (synonym: SynonymSchemaWithCollection) => {
        upsertSynonym.mutate(synonym);
        setEditingSynonym(null);
    };

    const handleDelete = (synonym: SynonymSchemaWithCollection) => {
        deleteSynonym.mutate(synonym);
    };

    const handleEdit = (synonym: SynonymSchemaWithCollection) => {
        setEditingSynonym(synonym);
    };

    const handleCancel = () => {
        setEditingSynonym(null);
    }

    let cardContent = <LoadingBox />;
    let cardStatus = CardStatus.LOADING;

    if (!synonymsQuery.isLoading) {
        if (synonymsQuery.isError || !synonymsQuery.data) {
            console.log(synonymsQuery.error);
            cardStatus = CardStatus.WARNING;
            cardContent = <ErrorMessageBox
                messageLine1={typesenseI18n['TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_1']}
                messageLine2={typesenseI18n['TYPESENSE_DASHBOARD_AJAX_ERROR_TEXT_2']}
            />;
        } else {
            cardStatus = CardStatus.OK;
            cardContent =
                <Stack width='100%' spacing={2}>
                    {!editingSynonym && (
                        <Box textAlign='center'>
                            <Button
                                variant='contained'
                                color='secondary'
                                onClick={() => setEditingSynonym(initialSynonym)}
                            >
                                {typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_ADD_SYNONYM_BUTTON_TEXT']}
                            </Button>
                        </Box>
                    )}
                    {editingSynonym && (
                        <SynonymForm synonym={editingSynonym} handleSave={handleSave} handleCancel={handleCancel}/>
                    )}
                    <SynonymsList synonyms={synonyms} handleEdit={handleEdit} handleDelete={handleDelete}/>
                </Stack>
        }
    }

    return (
        <DashboardCard
            cardIcon={<AbcIcon sx={{ fontSize:'1.4rem' }} />}
            cardTitle={typesenseI18n['TYPESENSE_DASHBOARD_CARD_SYNONYMS_TITLE']}
            cardContent={cardContent}
            cardStatus={cardStatus}
        />
    );
}
