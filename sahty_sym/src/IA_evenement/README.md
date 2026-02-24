# IA Evenement - Planning

## 1) Installer les dependances Python

```bash
pip install -r requirements.txt
```

## 2) Entrainer le modele

```bash
python train_model.py --dataset event_planning_dataset.csv --out models
```

## 3) Tester une prediction

```bash
python predict_planning.py "{""event_type"":""formation"",""mode"":""hybride"",""audience"":""mixte"",""level"":""intermediaire"",""duration_total_min"":240}"
```
