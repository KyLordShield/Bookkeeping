#!/usr/bin/env python3
"""
monthly_demand_miner.py  -  stdlib-only demand forecaster
Zero third-party dependencies. Uses weighted moving average + seasonal indexing.
"""
import json
import sys
from datetime import date
from collections import defaultdict

MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun',
               'Jul','Aug','Sep','Oct','Nov','Dec']


def parse_month(s):
    """Return (year, month) from 'YYYY-MM-01' or 'YYYY-MM'."""
    s = str(s).strip()[:7]
    parts = s.split('-')
    return int(parts[0]), int(parts[1])


def month_label(year, month):
    return f"{MONTH_NAMES[month - 1]} {year}"


def weighted_moving_average(values):
    """Linearly weighted moving average — recent values count more."""
    n = len(values)
    if n == 0:
        return 0.0
    weights = list(range(1, n + 1))
    total_w = sum(weights)
    return sum(v * w for v, w in zip(values, weights)) / total_w


def mean(values):
    return sum(values) / len(values) if values else 0.0


def stdev(values):
    if len(values) < 2:
        return 0.0
    m = mean(values)
    variance = sum((x - m) ** 2 for x in values) / (len(values) - 1)
    return variance ** 0.5


def seasonal_index(monthly_totals, month):
    """Ratio of this month's historical average to the global monthly average."""
    all_vals = list(monthly_totals.values())
    overall  = mean(all_vals)
    if overall == 0:
        return 1.0
    same_month = [v for (y, m), v in monthly_totals.items() if m == month]
    if not same_month:
        return 1.0
    return mean(same_month) / overall


def top_services_for_month(month_service_counts, year, month, service_names, top_n=3):
    key    = f"{year:04d}-{month:02d}-01"
    counts = month_service_counts.get(key, {})
    ranked = sorted(counts.items(), key=lambda x: -int(x[1]))[:top_n]
    return [service_names.get(str(sid), f"Service {sid}") for sid, _ in ranked]


def top_services_overall(month_service_counts, service_names, top_n=3):
    totals = defaultdict(int)
    for svc_map in month_service_counts.values():
        for sid, cnt in svc_map.items():
            totals[str(sid)] += int(cnt)
    ranked = sorted(totals.items(), key=lambda x: -x[1])[:top_n]
    return [service_names.get(sid, f"Service {sid}") for sid, _ in ranked]


def run(payload):
    month_service_counts = payload.get('month_service_counts', {})
    service_names        = payload.get('service_names', {})
    anchor_year          = int(payload.get('anchor_year', date.today().year))
    forecast_horizon     = int(payload.get('forecast_horizon', 3))

    cm_raw = str(payload.get('current_month', ''))
    try:
        cur_year, cur_month = parse_month(cm_raw)
    except Exception:
        today = date.today()
        cur_year, cur_month = today.year, today.month

    # ── Build (year, month) -> total map ──────────────────────────────────
    monthly_totals = {}
    for key, svc_counts in month_service_counts.items():
        try:
            y, m = parse_month(key)
        except Exception:
            continue
        monthly_totals[(y, m)] = sum(int(v) for v in svc_counts.values())

    # ── History for anchor_year ───────────────────────────────────────────
    history = []
    month_limit = cur_month if anchor_year == cur_year else 12
    for month in range(1, month_limit + 1):
        val      = monthly_totals.get((anchor_year, month), 0)
        top_svcs = top_services_for_month(
            month_service_counts, anchor_year, month, service_names
        )
        history.append({
            'month':          f"{anchor_year}-{month:02d}-01",
            'month_label':    month_label(anchor_year, month),
            'total_requests': val,
            'top_services':   [{'service_name': s, 'count': 0} for s in top_svcs],
        })

    # ── Ordered series for WMA ────────────────────────────────────────────
    sorted_entries = sorted(monthly_totals.items())   # [((y,m), total), ...]
    series         = [v for (_, _), v in sorted_entries]

    # ── Forecast next N months ────────────────────────────────────────────
    forecast    = []
    fc_year     = cur_year
    fc_month    = cur_month
    global_mean = mean(series) if series else 0.0

    for _ in range(forecast_horizon):
        fc_month += 1
        if fc_month > 12:
            fc_month = 1
            fc_year += 1

        window = series[-6:] if len(series) >= 6 else series[:]
        wma    = weighted_moving_average(window) if window else 0.0
        s_idx  = seasonal_index(monthly_totals, fc_month)
        pred   = max(0.0, wma * s_idx)
        pred_i = int(round(pred))

        # Confidence
        if len(series) < 3:
            confidence = 'low'
        else:
            sd = stdev(series[-6:]) if len(series) >= 2 else 0
            m6 = mean(series[-6:]) if series else 0
            confidence = 'low' if (m6 > 0 and sd / m6 > 0.6) else 'high'

        # Level
        if global_mean == 0:
            level = 'steady'
        elif pred > global_mean * 1.2:
            level = 'busy'
        elif pred < global_mean * 0.8:
            level = 'light'
        else:
            level = 'steady'

        # Advisory
        lbl = month_label(fc_year, fc_month)
        if level == 'busy':
            message = (f"{lbl} looks like a busy month. "
                       f"Consider scheduling additional staff capacity.")
        elif level == 'light':
            message = (f"{lbl} is projected to be quieter. "
                       f"Good time for internal reviews or proactive client outreach.")
        else:
            message = (f"{lbl} is expected to see steady demand. "
                       f"Maintain current resource allocation.")

        conf_note = ("Estimate based on limited data — treat as indicative."
                     if confidence == 'low'
                     else f"Based on {len(series)} month(s) of historical data.")

        top_svcs = top_services_for_month(
            month_service_counts, fc_year, fc_month, service_names
        )
        if not top_svcs:
            top_svcs = top_services_overall(month_service_counts, service_names)

        forecast.append({
            'month':                  f"{fc_year}-{fc_month:02d}-01",
            'month_label':            lbl,
            'predicted_requests':     pred_i,
            'level':                  level,
            'confidence_level':       confidence,
            'confidence_note':        conf_note,
            'predicted_top_services': top_svcs,
            'message':                message,
        })

    return {'history': history, 'forecast': forecast}


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No input file provided'}))
        sys.exit(1)
    try:
        with open(sys.argv[1], 'r', encoding='utf-8') as f:
            payload = json.load(f)
        result = run(payload)
        print(json.dumps(result, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({'error': str(e)}))
        sys.exit(1)