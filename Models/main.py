
import pandas as pd
import  numpy as np
import  datetime
import redis
import json
import _thread
import logging
from models.JindiaoModel import *

logging.basicConfig(level=logging.INFO, filename='model.log', filemode='a',
                    format='%(asctime)s - %(pathname)s[line:%(lineno)d] - %(levelname)s: %(message)s'
                    )

def matchModel(model, detailInfo):
    loans = json.loads(detailInfo)
    new_loans = model.filter(loans, skip_filter = False)
    if len(new_loans) > 0:
        data = pd.DataFrame(new_loans)
        result = model.predict(data)
        json_str = model.toStrategyJson(np.array(data['ListingId']), result)
        localRedis.publish("channelAiStrategy",json_str)
        logging.info(json_str)


if __name__ == "__main__":
    #read trainData
    df = pd.read_csv("./data/data100333.csv")
    label = np.array(df['delay']>0).astype(int)

    #trainModel
    model = JinModel();
    model.train(df, label, valid=False)

    #subscribe redis
    pool = redis.ConnectionPool(host="your_redis_host", password="your_redis_password", port=6742, db=4)
    localRedis = redis.Redis(connection_pool=pool)
    p = localRedis.pubsub()
    p.subscribe("channelDetailInfo");

    #deal with message
    for item in p.listen():
        if item['type'] == 'message':
            data = item['data'].decode()
            logging.debug("received data:")
            logging.debug(data)
            if data  == 'over':
                logging.info("Jindiao Model %s exit"%(item['channel'].decode()))
                p.unsubscribe('spub')
                break;
            _thread.start_new_thread(matchModel, (model, data,))
