import { GridFSBucket, MongoClient, type Db } from "mongodb";

const uri = process.env.IBLOGS_MONGODB_URL || "mongodb://mongo:27017";
const dbName = process.env.IBLOGS_MONGODB_DATABASE || "iblogs";

interface MongoGlobal {
  _mongoClientPromise?: Promise<MongoClient>;
}

const globalWithMongo = globalThis as unknown as MongoGlobal;

let clientPromise: Promise<MongoClient>;

if (process.env.NODE_ENV === "development") {
  if (!globalWithMongo._mongoClientPromise) {
    const client = new MongoClient(uri);
    globalWithMongo._mongoClientPromise = client.connect();
  }
  clientPromise = globalWithMongo._mongoClientPromise;
} else {
  const client = new MongoClient(uri);
  clientPromise = client.connect();
}

export async function getDb(): Promise<Db> {
  const client = await clientPromise;
  return client.db(dbName);
}

export async function getGridFsBucket(): Promise<GridFSBucket> {
  const db = await getDb();
  return new GridFSBucket(db, { bucketName: "log_content" });
}
