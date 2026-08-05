/**
 * Refugio do Cuscuzeiro - sincronizacao somente leitura com o painel Marketing.
 *
 * Antes de executar:
 * 1. troque SHARED_SECRET pelo mesmo valor de GOOGLE_ADS_SCRIPT_SECRET do servidor;
 * 2. visualize e autorize o script na conta correta;
 * 3. agende a execucao diaria no Google Ads.
 */
const CONFIG = Object.freeze({
  ENDPOINT_URL: '__ENDPOINT_URL__',
  SHARED_SECRET: 'COLE_AQUI_O_MESMO_SEGREDO_DO_ENV',
  CAMPAIGN_NAMES: __CAMPAIGN_NAMES__,
  LOOKBACK_DAYS: 30,
});

function main() {
  validateConfig();

  const account = AdsApp.currentAccount();
  const campaignMap = loadCampaigns();
  const period = reportingPeriod(account.getTimeZone(), CONFIG.LOOKBACK_DAYS);
  const metrics = loadDailyMetrics(period.start, period.end, campaignMap);
  const payload = {
    schema_version: 1,
    request_id: Utilities.getUuid(),
    generated_at: new Date().toISOString(),
    account: {
      customer_id: account.getCustomerId(),
      name: account.getName() || ('Google Ads ' + account.getCustomerId()),
      currency_code: account.getCurrencyCode(),
      time_zone: account.getTimeZone(),
    },
    campaigns: Object.keys(campaignMap).map(function (id) {
      return campaignMap[id];
    }),
    metrics: metrics,
  };

  const body = JSON.stringify(payload);
  const timestamp = String(Math.floor(Date.now() / 1000));
  const signature = 'sha256=' + hmacHex(timestamp + '.' + body, CONFIG.SHARED_SECRET);
  const response = UrlFetchApp.fetch(CONFIG.ENDPOINT_URL, {
    method: 'post',
    contentType: 'application/json; charset=utf-8',
    payload: body,
    headers: {
      'X-Refugio-Timestamp': timestamp,
      'X-Refugio-Signature': signature,
    },
    muteHttpExceptions: true,
  });

  const status = response.getResponseCode();
  const responseBody = response.getContentText();
  if (status < 200 || status >= 300) {
    throw new Error('O painel recusou a sincronizacao (HTTP ' + status + '): ' + responseBody);
  }
  console.log('Sincronizacao concluida: ' + responseBody);
}

function loadCampaigns() {
  const query = [
    'SELECT',
    '  campaign.id,',
    '  campaign.name,',
    '  campaign.status,',
    '  campaign.advertising_channel_type,',
    '  campaign_budget.amount_micros',
    'FROM campaign',
    'WHERE campaign.name IN (' + gaqlNames(CONFIG.CAMPAIGN_NAMES) + ')',
  ].join('\n');
  const rows = AdsApp.search(query);
  const campaigns = {};
  while (rows.hasNext()) {
    const row = rows.next();
    const id = String(row.campaign.id);
    campaigns[id] = {
      id: id,
      name: row.campaign.name,
      status: row.campaign.status,
      advertising_channel_type: row.campaign.advertisingChannelType,
      daily_budget_micros: String(row.campaignBudget.amountMicros || 0),
    };
  }
  if (Object.keys(campaigns).length === 0) {
    throw new Error('Nenhuma das campanhas configuradas foi encontrada nesta conta.');
  }
  return campaigns;
}

function loadDailyMetrics(start, end, campaignMap) {
  const query = [
    'SELECT',
    '  campaign.id,',
    '  segments.date,',
    '  metrics.cost_micros,',
    '  metrics.impressions,',
    '  metrics.clicks,',
    '  metrics.conversions,',
    '  metrics.all_conversions,',
    '  metrics.conversions_value',
    'FROM campaign',
    'WHERE campaign.name IN (' + gaqlNames(CONFIG.CAMPAIGN_NAMES) + ')',
    "  AND segments.date BETWEEN '" + start + "' AND '" + end + "'",
    'ORDER BY segments.date, campaign.id',
  ].join('\n');
  const rows = AdsApp.search(query);
  const metrics = [];
  while (rows.hasNext()) {
    const row = rows.next();
    const campaignId = String(row.campaign.id);
    if (!campaignMap[campaignId]) {
      continue;
    }
    metrics.push({
      campaign_id: campaignId,
      date: row.segments.date,
      cost_micros: String(row.metrics.costMicros || 0),
      impressions: String(row.metrics.impressions || 0),
      clicks: String(row.metrics.clicks || 0),
      conversions: decimalText(row.metrics.conversions || 0, 4),
      all_conversions: decimalText(row.metrics.allConversions || 0, 4),
      conversions_value: decimalText(row.metrics.conversionsValue || 0, 2),
    });
  }
  return metrics;
}

function reportingPeriod(timeZone, lookbackDays) {
  const endDate = new Date();
  const startDate = new Date(endDate.getTime() - (Math.max(1, lookbackDays) - 1) * 86400000);
  return {
    start: Utilities.formatDate(startDate, timeZone, 'yyyy-MM-dd'),
    end: Utilities.formatDate(endDate, timeZone, 'yyyy-MM-dd'),
  };
}

function gaqlNames(names) {
  return names.map(function (name) {
    return "'" + String(name).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
  }).join(', ');
}

function decimalText(value, scale) {
  const number = Number(value || 0);
  if (!isFinite(number) || number < 0) {
    throw new Error('O Google Ads retornou uma metrica numerica invalida.');
  }
  return number.toFixed(scale).replace(/\.?0+$/, '');
}

function hmacHex(value, secret) {
  const bytes = Utilities.computeHmacSha256Signature(value, secret, Utilities.Charset.UTF_8);
  return bytes.map(function (byte) {
    const unsigned = byte < 0 ? byte + 256 : byte;
    return ('0' + unsigned.toString(16)).slice(-2);
  }).join('');
}

function validateConfig() {
  if (!/^https:\/\//.test(CONFIG.ENDPOINT_URL)) {
    throw new Error('ENDPOINT_URL precisa usar HTTPS.');
  }
  if (CONFIG.SHARED_SECRET === 'COLE_AQUI_O_MESMO_SEGREDO_DO_ENV' || CONFIG.SHARED_SECRET.length < 32) {
    throw new Error('Preencha SHARED_SECRET com um segredo de pelo menos 32 caracteres.');
  }
  if (!Array.isArray(CONFIG.CAMPAIGN_NAMES) || CONFIG.CAMPAIGN_NAMES.length === 0) {
    throw new Error('Configure ao menos uma campanha em CAMPAIGN_NAMES.');
  }
}
