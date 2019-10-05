import  numpy as np
from datetime import datetime, timedelta
import  time
from sklearn.preprocessing import  OneHotEncoder
from sklearn.pipeline import Pipeline
from sklearn.model_selection import  train_test_split, cross_val_score
from sklearn.metrics import  roc_auc_score
import  lightgbm as lgb
import pickle
import json
import logging

ISOFORMAT = "%Y-%m-%d %H:%M:%S"
PPFORMAT = "%Y-%m-%dT%H:%M:%S.%f"


class JinModel:

    encoder = OneHotEncoder(sparse=False)
    TOP5_SCORE = 0.14
    TOP5_STRATEG_ID = 500
    TOP10_SCORE = 0.2
    TOP10_STRATEG_ID = 501
    TOP30_SCORE = 0.3
    TOP30_STRATEG_ID = 502
    TOP50_SCORE = 0.4
    TOP50_STRATEG_ID = 503
    TOP70_SCORE = 0.5
    TOP70_STRATEG_ID = 504



    lgb_parms = {
        "boosting_type": "gbdt",
        "num_leaves": 7,
        "max_depth": 4,
        "learning_rate": 0.05,
        "n_estimators": 130,
        "max_bin": 256,
        "subsample_for_bin": 20000,
        "min_split_gain": 0.001,
        "min_child_weight": 0.01,
        "min_child_samples": 9,
        "subsample": 0.8,
        "subsample_freq": 1,
        "colsample_bytree": 0.8,
        "seed": 2008,
        "n_jobs": -1,
        "verbose": 1,
        "class_weight": "balanced",
        "silent": False
    }
    estimator = lgb.LGBMClassifier(**lgb_parms)
    model_path = "lgb100333.pkl"

    def __init__(self):
        pass

    @staticmethod
    def get_features(data):
        feature = data[['Amount', 'AmountToReceive', 'CancelCount','FailedCount', 'HighestDebt', 'HighestPrincipal',
                        'NormalCount', 'OverdueLessCount', 'OverdueMoreCount', 'OwingAmount', 'OwingPrincipal',
                        'SuccessCount', 'TotalPrincipal', 'WasteCount']].copy()
        feature.fillna(0, inplace = True)
        feature['NormalCount_SuccessCount'] = data['NormalCount'] / (data['SuccessCount'] + 1e-3)
        feature['OverdueLessCount_NormalCount'] = data['OverdueLessCount'] / (
                    data['NormalCount'] + data['OverdueLessCount'] + data['OverdueMoreCount'] + 1e-3)
        feature['Amount_HighestDebt'] = data['Amount'] / (data['HighestDebt'] + data['Amount'])
        feature['OwingPre_HighestDebt'] = (data['OwingPrincipal']) / (data['HighestDebt'] + 1e-3)
        feature['total_count'] = data['SuccessCount'] + data['FailedCount'] + data['CancelCount']
        feature['DelaySuccessRatio'] = (data['OverdueLessCount'] + data['OverdueMoreCount']) / (
                    data['SuccessCount'] + 1e-3)
        feature['Amount1000'] = np.array(np.array(feature['Amount']) % 1000 == 0).astype(int)
        feature['Amount100'] = np.array(np.array(feature['Amount']) % 100 == 0).astype(int)
        feature['Amount10'] = np.array(np.array(feature['Amount']) % 10 == 0).astype(int)
        feature['overdue_count'] = data['OverdueLessCount'] + data['OverdueMoreCount']
        fetch_time = data['XFetchTime']
        first_success_borrow_time = data['FirstSuccessBorrowTime']
        feature['RegMonths'] = (fetch_time - first_success_borrow_time).apply(lambda x: x.days // 30)
        feature['avgRepay'] = (data['TotalPrincipal'] - data['OwingPrincipal']) / (feature['RegMonths'] + 1)
        feature['WasteSuccess'] = (data['WasteCount']) / (feature['SuccessCount'] + data['WasteCount'] + 1e-3)

        #category feature
        category_cols = ['CertificateValidate', 'CreditValidate', 'CreditCode', 'EducationDegree',
                         'Months', 'StudyStyle']
        for col in category_cols:
            feature[col] = data[col].astype("category")
        return  feature

    # 训练数据并保存模型
    # 其中封装了处理特征的pipeline
    def train(self, df, y, valid = False):
        df = df.copy()
        df['FirstSuccessBorrowTime'] = df['FirstSuccessBorrowTime'].apply(lambda x: datetime.strptime(x, ISOFORMAT))
        df['XFetchTime'] = df['XFetchTime'].apply(lambda x: datetime.strptime(x, ISOFORMAT))
        features = JinModel.get_features(df)
        if valid:
            scores = cross_val_score(self.estimator, features, y, scoring= "roc_auc", cv = 5)
            print("mean:", np.mean(scores), scores)
        else:
            self.estimator.fit(features, y)

    def save(self):
        with open(self.model_path, "wb") as f:
            pickle.dump(self.estimator, f)

    def load(self):
        with open(self.model_path, "rb") as f:
            self.estimator =  pickle.load(f)

    @staticmethod
    def filter(loan_infos, skip_filter = False):
        new_loan_infos =[]
        now = time.strftime(ISOFORMAT, time.localtime( time.time() ) )
        for l in loan_infos:
            if skip_filter:
                l['XFetchTime'] = datetime.now()
                l['FirstSuccessBorrowTime'] = datetime.strptime(l['FirstSuccessBorrowTime'], PPFORMAT)
                new_loan_infos.append(l)
            else:
                if l['PhoneValidate'] ==1 and l['OverdueMoreCount'] == 0 and l['Age']>23:
                    if l['NormalCount'] / (l['SuccessCount'] + 0.001) >=5 and l['OverdueLessCount'] / (l['NormalCount'] + 0.001) < 0.2:
                        if l['OwingAmount'] / (l['HighestDebt'] + 0.001) < 0.5 and l['Amount'] / (l['HighestPrincipal'] + 0.001) <= 2:
                            l['XFetchTime'] = datetime.now()
                            l['FirstSuccessBorrowTime'] = datetime.strptime(l['FirstSuccessBorrowTime'], PPFORMAT)
                            new_loan_infos.append(l)
        return new_loan_infos

    def predict(self, X):
        a = datetime.now()
        features = JinModel.get_features(X)
        b = datetime.now()
        result = self.estimator.predict_proba(features)[:,1]
        c = datetime.now()
        logging.debug("feature extract %f us"%((b - a).microseconds / len(X)))
        logging.debug("predict time %f us"%((c - b).microseconds / len(X)))
        return result

    def toStrategyJson(self, list_ids, scores):
        result = []
        list_ids = np.array(list_ids).astype(np.object)
        for list_id, score in zip(list_ids, scores):
            list_id_str = list_id
            if score < self.TOP5_SCORE:
                result.append(dict({"ListingId":list_id_str, "StrategyId":self.TOP5_STRATEG_ID}))
            elif score < self.TOP10_SCORE:
                result.append(dict({"ListingId": list_id_str, "StrategyId": self.TOP10_STRATEG_ID}))
            elif score < self.TOP30_SCORE:
                result.append(dict({"ListingId": list_id_str, "StrategyId": self.TOP30_STRATEG_ID}))
            elif score < self.TOP50_SCORE:
                result.append(dict({"ListingId": list_id_str, "StrategyId": self.TOP50_STRATEG_ID}))
            elif score < self.TOP70_SCORE:
                result.append(dict({"ListingId": list_id_str, "StrategyId": self.TOP70_STRATEG_ID}))
        print(result)
        return json.dumps(result)


