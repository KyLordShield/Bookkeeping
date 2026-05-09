#!/usr/bin/env python3
import json
import sys
from datetime import datetime


def parse_month(month_key):
    return datetime.strptime(month_key, "%Y-%m-%d")


def month_key(dt):
    return dt.strftime("%Y-%m-01")


def add_months(dt, count):
    month = dt.month - 1 + count
    year = dt.year + month // 12
    month = month % 12 + 1
    return datetime(year, month, 1)


def classify(predicted, baseline):
    if baseline <= 0:
        if predicted > 0:
            return "busy"
        return "steady"
    if predicted >= baseline * 1.2:
        return "busy"
    if predicted <= baseline * 0.8:
        return "light"
    return "steady"


def advisory(level, month_label, top_services):
    svc_text = ", ".join(top_services[:3]) if top_services else "core services"
    if level == "busy":
        return f"Forecast for {month_label} indicates a high-demand period. Proactive staffing and schedule capacity planning are recommended, with priority attention to {svc_text}."
    if level == "light":
        return f"Forecast for {month_label} indicates a lower-demand period. This window may be utilized for process optimization, documentation quality review, and backlog stabilization while monitoring {svc_text}."
    return f"Forecast for {month_label} indicates demand within normal operating range. Maintain standard service capacity, with routine monitoring of {svc_text}."


def get_top_services(service_counts, service_names, top_n=3):
    ranked = sorted(service_counts.items(), key=lambda kv: (-int(kv[1]), str(kv[0])))
    out = []
    for sid, count in ranked[:top_n]:
        name = service_names.get(str(sid), f"Service {sid}")
        out.append({"service_id": str(sid), "service_name": name, "count": int(count)})
    return out


def average(values):
    return (sum(values) / len(values)) if values else 0.0


def predict_total(target_month, totals_by_month):
    same_month_last_year = datetime(target_month.year - 1, target_month.month, 1)
    prev_key = month_key(same_month_last_year)
    if prev_key in totals_by_month:
        return int(totals_by_month[prev_key]), "historical_same_month"
    recent_vals = list(totals_by_month.values())[-6:]
    return int(round(average(recent_vals))), "fallback_recent_average"


def predict_service_mix(target_month, month_service_counts, historical_months, service_names):
    prev_key = month_key(datetime(target_month.year - 1, target_month.month, 1))
    source_counts = month_service_counts.get(prev_key, {})
    if not source_counts:
        source_counts = {}
        for month in historical_months[-6:]:
            for sid, cnt in month_service_counts.get(month, {}).items():
                sid_str = str(sid)
                source_counts[sid_str] = source_counts.get(sid_str, 0) + int(cnt)
    ranked = sorted(source_counts.items(), key=lambda kv: (-int(kv[1]), kv[0]))[:3]
    return [service_names.get(str(sid), f"Service {sid}") for sid, _ in ranked]


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "missing input file"}))
        return 1

    with open(sys.argv[1], "r", encoding="utf-8") as f:
        payload = json.load(f)

    month_service_counts = payload.get("month_service_counts", {})
    service_names = payload.get("service_names", {})
    current_month = payload.get("current_month")
    anchor_year = int(payload.get("anchor_year", datetime.now().year))
    horizon = min(3, max(1, int(payload.get("forecast_horizon", 3))))

    if not isinstance(month_service_counts, dict) or not month_service_counts:
        print(json.dumps({"history": [], "forecast": []}, ensure_ascii=False))
        return 0

    month_keys = sorted(month_service_counts.keys())
    if not current_month:
        current_month = month_keys[-1]

    historical_months = [m for m in month_keys if m <= current_month]
    totals_by_month = {
        m: int(sum(int(v) for v in (month_service_counts.get(m, {}) or {}).values()))
        for m in historical_months
    }
    baseline = average(list(totals_by_month.values())[-6:])

    history_rows = []
    for m in historical_months:
        if parse_month(m).year != anchor_year:
            continue
        total = totals_by_month[m]
        top = get_top_services(month_service_counts.get(m, {}) or {}, service_names, top_n=3)
        history_rows.append(
            {
                "month": m,
                "month_label": parse_month(m).strftime("%b %Y"),
                "total_requests": total,
                "top_services": top,
            }
        )

    anchor_months = [m for m in historical_months if parse_month(m).year == anchor_year and m <= current_month]
    last_month_dt = parse_month(anchor_months[-1]) if anchor_months else parse_month(historical_months[-1])
    forecast_rows = []
    for i in range(1, horizon + 1):
        target = add_months(last_month_dt, i)
        pred_total_raw, basis = predict_total(target, totals_by_month)
        pred_total = max(0, pred_total_raw)
        level = classify(pred_total, baseline)
        predicted_top = predict_service_mix(target, month_service_counts, historical_months, service_names)
        confidence_level = "high" if basis == "historical_same_month" else "low"
        confidence_note = (
            "Forecast confidence is high because same-month prior-year history is available."
            if confidence_level == "high"
            else "Forecast confidence is low because same-month prior-year history is unavailable; a recent-average fallback was used."
        )
        forecast_rows.append(
            {
                "month": month_key(target),
                "month_label": target.strftime("%b %Y"),
                "predicted_requests": pred_total,
                "level": level,
                "prediction_basis": basis,
                "confidence_level": confidence_level,
                "confidence_note": confidence_note,
                "predicted_top_services": predicted_top,
                "message": advisory(level, target.strftime("%B %Y"), predicted_top),
            }
        )

    print(json.dumps({"history": history_rows, "forecast": forecast_rows}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
