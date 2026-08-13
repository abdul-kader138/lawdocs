import { createRoot } from 'react-dom/client';
import { useEffect, useRef, useState } from 'react';
import { Filemanager, Willow, WillowDark } from '@svar-ui/react-filemanager';
import { RestDataProvider } from '@svar-ui/filemanager-data-provider';
import '@svar-ui/react-filemanager/all.css';

/**
 * Admins pick which user's drive to browse/manage; everyone else is locked to
 * their own. The scope lives in the RestDataProvider's base URL (a path segment,
 * "{apiBase}/{ownerId}"), not a header — RestDataProvider.send() ignores
 * setHeaders()/custom headers entirely, so a header-based approach silently
 * wouldn't reach the server. See app/Http/Controllers/Panel/DocumentController.php.
 */
function OwnerSwitcher({ users, ownerId, currentUserId, onChange }) {
    return (
        <div className="wma-doc-switcher">
            <label htmlFor="wma-doc-owner">Viewing documents for</label>
            <select
                id="wma-doc-owner"
                value={ownerId}
                onChange={(e) => onChange(Number(e.target.value))}
            >
                <option value={currentUserId}>My files</option>
                {users
                    .filter((u) => u.id !== currentUserId)
                    .map((u) => (
                        <option key={u.id} value={u.id}>
                            {u.name} ({u.email})
                        </option>
                    ))}
            </select>
        </div>
    );
}

function DocumentManagerApp({ apiBase, currentUserId, isAdmin }) {
    const [ownerId, setOwnerId] = useState(currentUserId);
    const [users, setUsers] = useState([]);
    const [ready, setReady] = useState(false);
    const [error, setError] = useState(null);
    const [initialData, setInitialData] = useState([]);
    const [drive, setDrive] = useState(undefined);
    const providerRef = useRef(null);

    useEffect(() => {
        if (!isAdmin) return;
        fetch(`${apiBase}/users`, { credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : []))
            .then(setUsers)
            .catch(() => {});
    }, [isAdmin, apiBase]);

    useEffect(() => {
        let cancelled = false;
        setReady(false);
        setError(null);

        const provider = new RestDataProvider(`${apiBase}/${ownerId}`);
        providerRef.current = provider;

        Promise.all([provider.loadFiles(), provider.loadInfo()])
            .then(([files, info]) => {
                if (cancelled) return;
                setInitialData(files || []);
                setDrive(Array.isArray(info) ? info[0] : info);
                setReady(true);
            })
            .catch(() => {
                if (!cancelled) setError('Could not load documents for this user.');
            });

        return () => {
            cancelled = true;
        };
    }, [ownerId, apiBase]);

    function initApi(api) {
        const provider = providerRef.current;
        api.setNext(provider);

        // Root children come from the initial `data` prop; every deeper folder is
        // marked lazy:true by the backend and fetched here on first expand.
        api.on('request-data', ({ id }) => {
            provider.loadFiles(id).then((data) => {
                api.exec('provide-data', { id, data });
            });
        });

        // download-file has no built-in handler in RestDataProvider — wire it
        // to the dedicated download endpoint ourselves.
        api.on('download-file', ({ id }) => {
            window.open(`${apiBase}/${ownerId}/download?id=${encodeURIComponent(id)}`, '_blank');
        });
    }

    if (error) {
        return <div className="wma-doc-status wma-doc-status--error">{error}</div>;
    }

    if (!ready) {
        return <div className="wma-doc-status">Loading documents…</div>;
    }

    return (
        <div className="wma-doc-app">
            {isAdmin && (
                <OwnerSwitcher
                    users={users}
                    ownerId={ownerId}
                    currentUserId={currentUserId}
                    onChange={setOwnerId}
                />
            )}
            <div className="wma-doc-filemanager">
                <Filemanager key={ownerId} data={initialData} drive={drive} init={initApi} />
            </div>
        </div>
    );
}

function ThemedApp(props) {
    const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'));

    useEffect(() => {
        const observer = new MutationObserver(() => {
            setDark(document.documentElement.classList.contains('dark'));
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    }, []);

    const Theme = dark ? WillowDark : Willow;

    return (
        <Theme>
            <DocumentManagerApp {...props} />
        </Theme>
    );
}

const rootEl = document.getElementById('document-manager-root');
if (rootEl) {
    const props = JSON.parse(rootEl.dataset.props);
    createRoot(rootEl).render(<ThemedApp {...props} />);
}
