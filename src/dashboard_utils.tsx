/**
 * @package  Typesense Plugin for Zen Cart
 * @author   marco-pm
 * @version  1.0.0
 * @see      https://github.com/marco-pm/zencart_typesense
 * @license  GNU Public License V2.0
 */

export async function fetchData<T>(ajaxMethod: string, isPost: boolean): Promise<T> {
    const response = await fetch(`ajax.php?act=ajaxAdminTypesenseDashboard&method=${ajaxMethod}`, {
        method: isPost ? 'POST' : 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    return await response.json() as T;
}
