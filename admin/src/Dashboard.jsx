import { useState, useEffect } from 'react';
import { Title } from 'react-admin';

const ENTRYPOINT = 'http://localhost:8081/api';

const cellStyle = { padding: '8px 12px', borderBottom: '1px solid #333', textAlign: 'left' };
const headerStyle = { ...cellStyle, fontWeight: 'bold', borderBottom: '2px solid #555' };

export default () => {
    const [summary, setSummary] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        const token = localStorage.getItem('token');
        fetch(`${ENTRYPOINT}/summary`, {
            headers: { Authorization: `Bearer ${token}` },
        })
            .then((r) => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(setSummary)
            .catch((e) => setError(e.message));
    }, []);

    if (error) {
        return <div style={{ padding: 20 }}><Title title="Summary" />Błąd: {error}</div>;
    }

    if (!summary) {
        return <div style={{ padding: 20 }}><Title title="Summary" />Ładowanie...</div>;
    }

    return (
        <div style={{ padding: 20 }}>
            <Title title="Summary" />

            <h2>Konta</h2>
            <table style={{ borderCollapse: 'collapse', width: '100%', marginBottom: 30 }}>
                <thead>
                <tr>
                    <th style={headerStyle}>Bank</th>
                    <th style={headerStyle}>Typ</th>
                    <th style={headerStyle}>Numer</th>
                    <th style={headerStyle}>Saldo</th>
                    <th style={headerStyle}>Waluta</th>
                </tr>
                </thead>
                <tbody>
                {summary.accounts.map((account) => (
                    <tr key={account.id}>
                        <td style={cellStyle}>{account.bank}</td>
                        <td style={cellStyle}>{account.type}</td>
                        <td style={cellStyle}>{account.account_number}</td>
                        <td style={cellStyle}>
                            {account.saldo.amount}
                            {account.missing_rate && (
                                <span style={{ color: 'red', marginLeft: 8 }}>(brak kursu)</span>
                            )}
                        </td>
                        <td style={cellStyle}>{account.currency}</td>
                    </tr>
                ))}
                </tbody>
            </table>

            <h2>Podsumowanie wg typu (PLN)</h2>
            <table style={{ borderCollapse: 'collapse', width: '100%', maxWidth: 400, marginBottom: 30 }}>
                <thead>
                <tr>
                    <th style={headerStyle}>Typ</th>
                    <th style={headerStyle}>PLN</th>
                </tr>
                </thead>
                <tbody>
                {Object.entries(summary.byType).map(([type, data]) => (
                    <tr key={type}>
                        <td style={cellStyle}>{type}</td>
                        <td style={cellStyle}>{data.pln.amount}</td>
                    </tr>
                ))}
                </tbody>
            </table>

            <h2>Podsumowanie wg gospodarstwa (PLN)</h2>
            <table style={{ borderCollapse: 'collapse', width: '100%', maxWidth: 400, marginBottom: 30 }}>
                <tbody>
                {Object.entries(summary.byHousehold).map(([household, money]) => (
                    <tr key={household}>
                        <td style={cellStyle}>{household}</td>
                        <td style={cellStyle}>{money.amount}</td>
                    </tr>
                ))}
                </tbody>
            </table>

            <p><strong>Złoto:</strong> {summary.gold.amount} PLN</p>
            <p><strong>Saldo emerytalne:</strong> {summary.retirementBalance.amount} PLN</p>
            <p><strong>Saldo emerytalne + złoto:</strong> {summary.retirementBalanceWithGold.amount} PLN</p>

            {summary.accountsMissingRate.length > 0 && (
                <p style={{ color: 'red' }}>
                    Brak kursu dla: {summary.accountsMissingRate.join(', ')}
                </p>
            )}
        </div>
    );
};