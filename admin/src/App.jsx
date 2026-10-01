import { useState } from 'react';
import { Navigate, Route } from 'react-router-dom';
import { CustomRoutes, Resource } from 'react-admin';
import {
    fetchHydra as baseFetchHydra,
    HydraAdmin,
    hydraDataProvider as baseHydraDataProvider,
    useIntrospection,
} from '@api-platform/admin';
import { parseHydraDocumentation } from '@api-platform/api-doc-parser';

import Dashboard from './Dashboard';

const ENTRYPOINT = 'http://localhost:8081/api';

const getHeaders = () =>
    localStorage.getItem('token')
        ? { Authorization: `Bearer ${localStorage.getItem('token')}` }
        : {};

const fetchHydra = (url, options = {}) =>
    baseFetchHydra(url, {
        ...options,
        headers: getHeaders,
    });

const RedirectToLogin = () => {
    const introspect = useIntrospection();

    if (localStorage.getItem('token')) {
        introspect();
        return <></>;
    }
    return <Navigate to="/login" />;
};

const apiDocumentationParser = (setRedirectToLogin) => async () => {
    try {
        setRedirectToLogin(false);
        return await parseHydraDocumentation(ENTRYPOINT, { headers: getHeaders });
    } catch (result) {
        const { api, response, status } = result;
        if (status !== 401 || !response) {
            throw result;
        }

        localStorage.removeItem('token');
        setRedirectToLogin(true);

        return { api, response, status };
    }
};

const dataProvider = (setRedirectToLogin) =>
    baseHydraDataProvider({
        entrypoint: ENTRYPOINT,
        httpClient: fetchHydra,
        apiDocumentationParser: apiDocumentationParser(setRedirectToLogin),
    });

const authProvider = {
    login: async ({ username, password }) => {
        const response = await fetch(`${ENTRYPOINT}/login_check`, {
            method: 'POST',
            body: JSON.stringify({ email: username, password }),
            headers: new Headers({ 'Content-Type': 'application/json' }),
        });

        if (response.status < 200 || response.status >= 300) {
            throw new Error('Nieprawidłowy email lub hasło.');
        }

        const auth = await response.json();
        localStorage.setItem('token', auth.token);
    },
    logout: () => {
        localStorage.removeItem('token');
        return Promise.resolve();
    },
    checkAuth: () => (localStorage.getItem('token') ? Promise.resolve() : Promise.reject()),
    checkError: (error) => {
        if (error.status === 401 || error.status === 403) {
            localStorage.removeItem('token');
            return Promise.reject();
        }
        return Promise.resolve();
    },
    getIdentity: () => Promise.reject('brak danych identyfikacyjnych'),
    getPermissions: () => Promise.resolve(''),
};

export default () => {
    const [redirectToLogin, setRedirectToLogin] = useState(false);

    return (
        <HydraAdmin
            dataProvider={dataProvider(setRedirectToLogin)}
            authProvider={authProvider}
            entrypoint={ENTRYPOINT}
            dashboard={Dashboard}
        >
            {redirectToLogin && (
                <CustomRoutes>
                    <Route path="/" element={<RedirectToLogin />} />
                    <Route path="/:any" element={<RedirectToLogin />} />
                </CustomRoutes>
            )}
        </HydraAdmin>
    );
};